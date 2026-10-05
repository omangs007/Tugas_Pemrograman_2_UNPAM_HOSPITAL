<?php

namespace Tests\Feature\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\Availability;
use App\Enums\DoctorStatus;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use App\Services\Scheduling\AvailabilityFilter;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\SessionAvailability;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * T5: satu sumber jadwal. "Sekarang" tetap Senin 2026-10-05 08:00 (Asia/Jakarta); jendela default 30 hari
 * (hari ke-30 = 2026-11-04, hari ke-31 = 2026-11-05) dan batas pendaftaran online 60 menit.
 */
class DoctorAvailabilityQueryTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    private DoctorAvailabilityQuery $query;

    private Clinic $clinic;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00'));
        $this->seed(SettingsSeeder::class);

        $this->query = $this->app->make(DoctorAvailabilityQuery::class);
        $this->clinic = Clinic::factory()->create();
        $this->doctor = Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]);
    }

    // ------------------------------------------------------------------ pembantu

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSession(array $overrides = [], ?Doctor $doctor = null, ?Clinic $clinic = null): PracticeSession
    {
        $doctor ??= $this->doctor;
        $clinic ??= $this->clinic;

        return $this->createSession(array_merge([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'doctor_schedule_id' => null,
            'session_date' => '2026-10-05',
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'quota' => 10,
            'status' => SessionStatus::Open,
        ], $overrides));
    }

    /**
     * Sesi dengan satu pendaftaran aktif pada kuota 1 sehingga `remaining` = 0.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeFullSession(array $overrides = []): PracticeSession
    {
        $session = $this->makeSession(array_merge(['quota' => 1], $overrides));
        $this->bookAppointment($session, AppointmentStatus::Confirmed);

        return $session->fresh();
    }

    private function makeException(ExceptionType $type): ScheduleException
    {
        return $this->createException([
            'type' => $type,
            'doctor_id' => $type === ExceptionType::DoctorLeave || $type === ExceptionType::Other ? $this->doctor->id : null,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
            'reason' => 'Alasan uji',
        ]);
    }

    private function buildSession(string $mutation): PracticeSession
    {
        return match ($mutation) {
            'past' => $this->makeSession(['start_time' => '06:00:00', 'end_time' => '07:00:00']),
            'ends_now' => $this->makeSession(['start_time' => '07:00:00', 'end_time' => '08:00:00']),
            'past_cancelled' => $this->makeSession([
                'start_time' => '06:00:00',
                'end_time' => '07:00:00',
                'status' => SessionStatus::Cancelled,
                'schedule_exception_id' => $this->makeException(ExceptionType::DoctorLeave)->id,
            ]),
            'leave' => $this->makeSession(['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $this->makeException(ExceptionType::DoctorLeave)->id]),
            'holiday_hospital' => $this->makeSession(['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $this->makeException(ExceptionType::HospitalHoliday)->id]),
            'holiday_national' => $this->makeSession(['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $this->makeException(ExceptionType::NationalHoliday)->id]),
            'cancelled_other' => $this->makeSession(['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $this->makeException(ExceptionType::Other)->id]),
            'cancelled_plain' => $this->makeSession(['status' => SessionStatus::Cancelled]),
            'needs_review' => $this->makeSession([
                'status' => SessionStatus::NeedsReview,
                'pending_change' => ['source' => 'doctor_inactive', 'doctor_id' => $this->doctor->id],
            ]),
            'needs_review_full' => $this->makeFullSession([
                'status' => SessionStatus::NeedsReview,
                'pending_change' => ['source' => 'doctor_inactive', 'doctor_id' => $this->doctor->id],
            ]),
            'closed' => $this->makeSession(['status' => SessionStatus::Closed]),
            'closed_full' => $this->makeFullSession(['status' => SessionStatus::Closed]),
            'doctor_inactive' => $this->deactivateDoctor($this->makeSession()),
            'clinic_inactive' => $this->deactivateClinic($this->makeSession()),
            'full' => $this->makeFullSession(),
            'full_inside_cutoff' => $this->makeFullSession(['start_time' => '08:30:00', 'end_time' => '11:00:00']),
            'inside_cutoff' => $this->makeSession(['start_time' => '08:30:00', 'end_time' => '11:00:00']),
            'exact_cutoff' => $this->makeSession(['start_time' => '09:00:00', 'end_time' => '12:00:00']),
            'in_progress' => $this->makeSession(['start_time' => '07:00:00', 'end_time' => '10:00:00']),
            'clinic_not_bookable' => $this->makeSessionInUnbookableClinic(),
            'day_30' => $this->makeSession(['session_date' => '2026-11-04']),
            'day_31' => $this->makeSession(['session_date' => '2026-11-05']),
            'available' => $this->makeSession(),
        };
    }

    private function deactivateDoctor(PracticeSession $session): PracticeSession
    {
        $this->doctor->forceFill(['status' => DoctorStatus::Inactive])->save();

        return $session;
    }

    private function deactivateClinic(PracticeSession $session): PracticeSession
    {
        $this->clinic->forceFill(['is_active' => false])->save();

        return $session;
    }

    private function makeSessionInUnbookableClinic(): PracticeSession
    {
        $this->clinic->forceFill(['is_bookable' => false])->save();

        return $this->makeSession();
    }

    /**
     * @return array<string, array{string, string, Availability, Availability}>
     */
    public static function classificationRows(): array
    {
        $now = '2026-10-05 08:00:00';

        return [
            '1 sudah lewat' => ['past', $now, Availability::Past, Availability::Past],
            '1 berakhir tepat sekarang' => ['ends_now', $now, Availability::Past, Availability::Past],
            '1 lewat mengalahkan batal' => ['past_cancelled', $now, Availability::Past, Availability::Past],
            '2 cuti dokter' => ['leave', $now, Availability::Leave, Availability::Leave],
            '2 libur rumah sakit' => ['holiday_hospital', $now, Availability::Holiday, Availability::Holiday],
            '2 libur nasional' => ['holiday_national', $now, Availability::Holiday, Availability::Holiday],
            '2 batal jenis lain' => ['cancelled_other', $now, Availability::Cancelled, Availability::Cancelled],
            '2 batal tanpa pengecualian' => ['cancelled_plain', $now, Availability::Cancelled, Availability::Cancelled],
            '3 perlu tindak lanjut' => ['needs_review', $now, Availability::UnderReview, Availability::UnderReview],
            '3 perlu tindak lanjut mengalahkan penuh' => ['needs_review_full', $now, Availability::UnderReview, Availability::UnderReview],
            '4 ditutup' => ['closed', $now, Availability::Closed, Availability::Closed],
            '4 ditutup mengalahkan penuh' => ['closed_full', $now, Availability::Closed, Availability::Closed],
            '4 dokter nonaktif' => ['doctor_inactive', $now, Availability::Closed, Availability::Closed],
            '4 poliklinik nonaktif' => ['clinic_inactive', $now, Availability::Closed, Availability::Closed],
            '5 penuh' => ['full', $now, Availability::Full, Availability::Full],
            '5 penuh mengalahkan batas online' => ['full_inside_cutoff', $now, Availability::Full, Availability::Full],
            '6 dalam batas online' => ['inside_cutoff', $now, Availability::BookingClosed, Availability::Available],
            '6 tepat pada mulai dikurangi batas' => ['exact_cutoff', $now, Availability::BookingClosed, Availability::Available],
            '6 satu detik sebelum batas' => ['exact_cutoff', '2026-10-05 07:59:59', Availability::Available, Availability::Available],
            '6 sesi sedang berjalan' => ['in_progress', $now, Availability::BookingClosed, Availability::Available],
            '6 satu detik sebelum selesai' => ['in_progress', '2026-10-05 09:59:59', Availability::BookingClosed, Availability::Available],
            '6 poliklinik tidak dibuka untuk pendaftaran' => ['clinic_not_bookable', $now, Availability::BookingClosed, Availability::BookingClosed],
            '6 hari ke-30 masih dalam jendela' => ['day_30', $now, Availability::Available, Availability::Available],
            '6 hari ke-31 di luar jendela' => ['day_31', $now, Availability::BookingClosed, Availability::BookingClosed],
            '7 tersedia' => ['available', $now, Availability::Available, Availability::Available],
        ];
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    // ------------------------------------------------------------------ classify (F3-R12)

    #[DataProvider('classificationRows')]
    public function test_classify_follows_the_priority_table_for_online_and_counter(string $mutation, string $now, Availability $online, Availability $counter): void
    {
        $session = $this->buildSession($mutation)->load(['doctor', 'clinic', 'exception']);
        $rules = $this->app->make(SettingsService::class)->bookingRules();
        $at = CarbonImmutable::parse($now);

        $this->assertSame($online, $this->query->classify($session, $rules, $at, false));
        $this->assertSame($counter, $this->query->classify($session, $rules, $at, true));

        $item = $this->query->forSession($session, false, $at);

        $this->assertSame($online, $item->availability);
        $this->assertSame($online === Availability::Available, $item->isBookableOnline);
        $this->assertSame($counter === Availability::Available, $item->isBookableCounter);
        $this->assertSame($counter, $this->query->forSession($session, true, $at)->availability);
    }

    public function test_remaining_is_quota_minus_booked_count_and_never_negative(): void
    {
        $session = $this->makeSession(['quota' => 3]);
        $this->bookAppointment($session->fresh(), AppointmentStatus::Confirmed);
        $this->bookAppointment($session->fresh(), AppointmentStatus::Cancelled);

        $item = $this->query->forSession($session->fresh());

        $this->assertSame(2, $item->remaining);
        $this->assertSame(0, $this->query->forSession($this->makeFullSession())->remaining);
    }

    public function test_window_boundary_moves_at_midnight(): void
    {
        $session = $this->makeSession(['session_date' => '2026-11-05'])->load(['doctor', 'clinic', 'exception']);
        $rules = $this->app->make(SettingsService::class)->bookingRules();

        $this->assertSame(
            Availability::BookingClosed,
            $this->query->classify($session, $rules, CarbonImmutable::parse('2026-10-05 23:59:00'), false),
        );
        $this->assertSame(
            Availability::Available,
            $this->query->classify($session, $rules, CarbonImmutable::parse('2026-10-06 00:00:00'), false),
        );
    }

    public function test_cutoff_follows_the_booking_setting(): void
    {
        $this->app->make(SettingsService::class)->setMany(['booking.online_cutoff_minutes' => 120]);
        $this->app->make(SettingsService::class)->forget();
        $session = $this->makeSession(['start_time' => '09:30:00', 'end_time' => '12:00:00']);

        $item = $this->query->forSession($session);

        $this->assertSame(Availability::BookingClosed, $item->availability);
        $this->assertFalse($item->isBookableOnline);
        $this->assertTrue($item->isBookableCounter);
    }

    // ------------------------------------------------------------------ sessions()

    public function test_sessions_lists_visible_doctors_in_date_then_time_order_and_hides_past_sessions(): void
    {
        $later = $this->makeSession(['session_date' => '2026-10-06', 'start_time' => '09:00:00', 'end_time' => '11:00:00']);
        $afternoon = $this->makeSession(['start_time' => '14:00:00', 'end_time' => '16:00:00']);
        $morning = $this->makeSession(['start_time' => '09:00:00', 'end_time' => '11:00:00']);
        $ended = $this->makeSession(['start_time' => '06:00:00', 'end_time' => '07:00:00']);
        $beyondWindow = $this->makeSession(['session_date' => '2026-11-05']);
        $lastDay = $this->makeSession(['session_date' => '2026-11-04']);

        $result = $this->query->sessions(new AvailabilityFilter);

        $this->assertSame(
            [$morning->id, $afternoon->id, $later->id, $lastDay->id],
            $result->map(fn (SessionAvailability $item): int => $item->session->id)->all(),
        );
        $this->assertNotContains($ended->id, $result->map(fn (SessionAvailability $item): int => $item->session->id)->all());
        $this->assertNotContains($beyondWindow->id, $result->map(fn (SessionAvailability $item): int => $item->session->id)->all());
    }

    public function test_include_past_keeps_ended_sessions_labelled_past(): void
    {
        $ended = $this->makeSession(['start_time' => '06:00:00', 'end_time' => '07:00:00']);
        $upcoming = $this->makeSession();

        $result = $this->query->sessions(new AvailabilityFilter(includePast: true));

        $this->assertSame([$ended->id, $upcoming->id], $result->map(fn (SessionAvailability $item): int => $item->session->id)->all());
        $this->assertSame(Availability::Past, $result->first()->availability);
        $this->assertFalse($result->first()->isBookableOnline);
    }

    public function test_sessions_of_doctors_that_are_not_publicly_visible_are_never_returned(): void
    {
        $inactive = Doctor::factory()->withSpecialties()->inactive()->create(['clinic_id' => $this->clinic->id]);
        $archived = Doctor::factory()->withSpecialties()->archived()->create(['clinic_id' => $this->clinic->id]);
        $dormantClinic = Clinic::factory()->inactive()->create();
        $inDormantClinic = Doctor::factory()->withSpecialties()->create(['clinic_id' => $dormantClinic->id]);
        $visible = $this->makeSession();

        foreach ([$inactive, $archived, $inDormantClinic] as $hidden) {
            $this->makeSession([], $hidden, $this->clinic);
        }

        $result = $this->query->sessions(new AvailabilityFilter);

        $this->assertSame([$visible->id], $result->map(fn (SessionAvailability $item): int => $item->session->id)->all());
    }

    public function test_sessions_filters_by_date_range_doctor_clinic_specialty_and_name(): void
    {
        $otherClinic = Clinic::factory()->create();
        $other = Doctor::factory()->withSpecialties()->create(['clinic_id' => $otherClinic->id, 'name' => 'Zulkifli Hakim']);
        $mine = $this->makeSession(['session_date' => '2026-10-07']);
        $theirs = $this->makeSession(['session_date' => '2026-10-08'], $other, $otherClinic);

        $ids = fn (AvailabilityFilter $filter): array => $this->query->sessions($filter)
            ->map(fn (SessionAvailability $item): int => $item->session->id)->all();

        $this->assertSame([$mine->id], $ids(new AvailabilityFilter(date: CarbonImmutable::parse('2026-10-07'))));
        $this->assertSame([$theirs->id], $ids(new AvailabilityFilter(from: CarbonImmutable::parse('2026-10-08'), to: CarbonImmutable::parse('2026-10-09'))));
        $this->assertSame([$mine->id], $ids(new AvailabilityFilter(doctorId: $this->doctor->id)));
        $this->assertSame([$theirs->id], $ids(new AvailabilityFilter(clinicId: $otherClinic->id)));
        $this->assertSame([$theirs->id], $ids(new AvailabilityFilter(q: 'Zulkifli')));

        $specialtyId = (int) $other->specialties()->first()->id;
        $this->assertSame([$theirs->id], $ids(new AvailabilityFilter(specialtyId: $specialtyId)));
    }

    public function test_for_loket_changes_the_displayed_label_but_not_the_two_flags(): void
    {
        $this->makeSession(['start_time' => '08:30:00', 'end_time' => '11:00:00']);

        $online = $this->query->sessions(new AvailabilityFilter)->sole();
        $counter = $this->query->sessions(new AvailabilityFilter(forLoket: true))->sole();

        $this->assertSame(Availability::BookingClosed, $online->availability);
        $this->assertSame(Availability::Available, $counter->availability);

        foreach ([$online, $counter] as $item) {
            $this->assertFalse($item->isBookableOnline);
            $this->assertTrue($item->isBookableCounter);
        }
    }

    public function test_for_session_returns_the_same_result_as_sessions_for_the_same_session_jdw12(): void
    {
        $this->makeSession(['start_time' => '08:30:00', 'end_time' => '11:00:00']);
        $this->makeSession(['session_date' => '2026-10-06']);
        $this->makeFullSession(['session_date' => '2026-10-07']);
        $this->makeSession(['session_date' => '2026-10-08', 'status' => SessionStatus::Closed]);
        $this->makeSession([
            'session_date' => '2026-10-09',
            'status' => SessionStatus::Cancelled,
            'schedule_exception_id' => $this->createException([
                'type' => ExceptionType::DoctorLeave,
                'doctor_id' => $this->doctor->id,
                'start_date' => '2026-10-09',
                'end_date' => '2026-10-09',
                'reason' => 'Cuti',
            ])->id,
        ]);

        foreach ([false, true] as $forLoket) {
            $listed = $this->query->sessions(new AvailabilityFilter(forLoket: $forLoket));

            $this->assertCount(5, $listed);

            foreach ($listed as $item) {
                $single = $this->query->forSession(PracticeSession::query()->findOrFail($item->session->id), $forLoket);

                $this->assertSame($item->remaining, $single->remaining);
                $this->assertSame($item->availability, $single->availability);
                $this->assertSame($item->isBookableOnline, $single->isBookableOnline);
                $this->assertSame($item->isBookableCounter, $single->isBookableCounter);
            }
        }
    }

    public function test_manual_extra_session_is_bookable_online_jdw13(): void
    {
        $manual = $this->makeSession(['doctor_schedule_id' => null, 'session_date' => '2026-10-06']);

        $item = $this->query->sessions(new AvailabilityFilter)->sole();

        $this->assertSame($manual->id, $item->session->id);
        $this->assertNull($item->session->doctor_schedule_id);
        $this->assertTrue($item->isBookableOnline);
        $this->assertTrue($item->isBookableCounter);
        $this->assertSame(Availability::Available, $item->availability);
    }

    public function test_sessions_do_not_trigger_n_plus_one_queries_in_strict_mode(): void
    {
        $this->makeSession();
        $this->query->sessions(new AvailabilityFilter);

        $small = $this->countQueries(fn () => $this->touchEverything($this->query->sessions(new AvailabilityFilter)));

        foreach (range(1, 5) as $index) {
            $doctor = Doctor::factory()->withSpecialties(2)->create(['clinic_id' => $this->clinic->id]);

            foreach (range(1, 3) as $day) {
                $this->makeSession(['session_date' => CarbonImmutable::parse('2026-10-05')->addDays($day + $index)->toDateString()], $doctor);
            }
        }

        $large = $this->countQueries(fn () => $this->touchEverything($this->query->sessions(new AvailabilityFilter)));

        $this->assertCount(16, $this->query->sessions(new AvailabilityFilter));
        $this->assertSame($small, $large);
    }

    /**
     * @param  iterable<SessionAvailability>  $items
     */
    private function touchEverything(iterable $items): void
    {
        foreach ($items as $item) {
            $session = $item->session;

            $session->doctor->specialties->count();
            $session->doctor->firstMedia('photo');
            $session->doctor->display_name;
            $session->clinic->name;
            $session->exception?->reason;
        }
    }

    // ------------------------------------------------------------------ doctors()

    public function test_doctors_lists_only_publicly_visible_doctors_with_their_availabilities(): void
    {
        Doctor::factory()->withSpecialties()->inactive()->create(['clinic_id' => $this->clinic->id]);
        Doctor::factory()->withSpecialties()->archived()->create(['clinic_id' => $this->clinic->id]);
        $dormant = Clinic::factory()->inactive()->create();
        Doctor::factory()->withSpecialties()->create(['clinic_id' => $dormant->id]);
        $empty = Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]);

        $tomorrow = $this->makeSession(['session_date' => '2026-10-06']);
        $today = $this->makeSession();
        $this->makeSession(['start_time' => '06:00:00', 'end_time' => '07:00:00']);
        $this->makeSession(['session_date' => '2026-11-05']);

        $page = $this->query->doctors(new AvailabilityFilter);

        $this->assertSame(2, $page->total());
        $byId = $page->getCollection()->keyBy('id');

        $this->assertEqualsCanonicalizing([$this->doctor->id, $empty->id], $byId->keys()->all());
        $this->assertSame(
            [$today->id, $tomorrow->id],
            $byId[$this->doctor->id]->availabilities->map(fn (SessionAvailability $item): int => $item->session->id)->all(),
        );
        $this->assertCount(0, $byId[$empty->id]->availabilities);
    }

    public function test_doctors_with_a_date_include_leave_sessions_and_exclude_doctors_without_sessions_that_day(): void
    {
        $onLeave = Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]);
        $without = Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]);
        $leave = $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $onLeave->id,
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-07',
            'reason' => 'Cuti tahunan',
        ]);
        $this->makeSession(['session_date' => '2026-10-07', 'status' => SessionStatus::Cancelled, 'schedule_exception_id' => $leave->id], $onLeave);
        $this->makeSession(['session_date' => '2026-10-07']);
        $this->makeSession(['session_date' => '2026-10-08'], $without);

        $page = $this->query->doctors(new AvailabilityFilter(date: CarbonImmutable::parse('2026-10-07')));
        $byId = $page->getCollection()->keyBy('id');

        $this->assertEqualsCanonicalizing([$this->doctor->id, $onLeave->id], $byId->keys()->all());
        $this->assertSame(Availability::Leave, $byId[$onLeave->id]->availabilities->sole()->availability);
        $this->assertSame(Availability::Available, $byId[$this->doctor->id]->availabilities->sole()->availability);
    }

    public function test_doctors_filters_by_name_specialty_and_clinic_and_paginates(): void
    {
        $otherClinic = Clinic::factory()->create();
        $other = Doctor::factory()->withSpecialties()->create(['clinic_id' => $otherClinic->id, 'name' => 'Zulkifli Hakim']);
        $this->makeSession();

        $this->assertSame([$other->id], $this->query->doctors(new AvailabilityFilter(q: 'Zulkifli'))->getCollection()->modelKeys());
        $this->assertSame([$other->id], $this->query->doctors(new AvailabilityFilter(clinicId: $otherClinic->id))->getCollection()->modelKeys());
        $this->assertSame(
            [$other->id],
            $this->query->doctors(new AvailabilityFilter(specialtyId: (int) $other->specialties()->first()->id))->getCollection()->modelKeys(),
        );

        $page = $this->query->doctors(new AvailabilityFilter, 1);

        $this->assertSame(2, $page->total());
        $this->assertCount(1, $page->getCollection());
        $this->assertSame(2, $page->lastPage());
    }

    public function test_doctors_load_sessions_once_per_page_without_n_plus_one(): void
    {
        $this->makeSession();
        $this->query->doctors(new AvailabilityFilter);

        $small = $this->countQueries(fn () => $this->touchDoctors($this->query->doctors(new AvailabilityFilter)));

        foreach (range(1, 6) as $index) {
            $doctor = Doctor::factory()->withSpecialties(2)->create(['clinic_id' => $this->clinic->id]);

            foreach (range(1, 3) as $day) {
                $this->makeSession(['session_date' => CarbonImmutable::parse('2026-10-05')->addDays($day)->toDateString()], $doctor);
            }
        }

        $large = $this->countQueries(fn () => $this->touchDoctors($this->query->doctors(new AvailabilityFilter)));

        $this->assertSame($small, $large);
    }

    /**
     * @param  iterable<Doctor>  $doctors
     */
    private function touchDoctors(iterable $doctors): void
    {
        foreach ($doctors as $doctor) {
            $doctor->clinic->name;
            $doctor->primary_specialty;
            $doctor->firstMedia('photo');

            foreach ($doctor->availabilities as $item) {
                $item->session->clinic->name;
                $item->session->exception?->reason;
                $item->session->doctor->display_name;
            }
        }
    }
}
