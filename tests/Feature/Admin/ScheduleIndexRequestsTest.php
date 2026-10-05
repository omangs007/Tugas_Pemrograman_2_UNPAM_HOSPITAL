<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\DoctorScheduleIndexRequest;
use App\Http\Requests\Admin\PracticeSessionIndexRequest;
use App\Http\Requests\Admin\ScheduleExceptionIndexRequest;
use App\Http\Requests\ListFilterRequest;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * F3-R22 / 12 V-45, V-46 / VAL-07 (parameter admin): parameter filter admin #35 yang tidak valid diabaikan,
 * tidak pernah galat 500. Filter dibaca langsung dari Form Request; kueri diuji lewat applyTo().
 */
class ScheduleIndexRequestsTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 5 Oktober 2026 pukul 08.00 WIB.
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));
    }

    /**
     * @template T of ListFilterRequest
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $query
     * @return T
     */
    private function resolve(string $class, array $query): ListFilterRequest
    {
        $request = $class::create('/_uji/filter', 'GET', $query);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        return $request;
    }

    // --- Templat ---------------------------------------------------------------------------------------

    public function test_schedule_filters_accept_valid_values(): void
    {
        $doctor = $this->createDoctor(['name' => 'Raka Wirawan']);

        $request = $this->resolve(DoctorScheduleIndexRequest::class, [
            'dokter' => $doctor->slug,
            'hari' => '3',
            'status' => 'aktif',
            'urut' => '-hari',
        ]);

        $this->assertEquals(['dokter' => $doctor->slug, 'hari' => '3', 'status' => 'aktif', 'urut' => '-hari'], $request->filters());
    }

    public function test_schedule_filters_ignore_invalid_values_without_an_error(): void
    {
        $request = $this->resolve(DoctorScheduleIndexRequest::class, [
            'dokter' => 'tidak-ada',
            'hari' => '9',
            'status' => 'zzz',
            'urut' => 'kuota',
            'kembali' => 'https://situs-lain.test',
        ]);

        $this->assertSame([], $request->filters());
        $this->assertSame('Filter tidak valid dan diabaikan.', session('warning'));
    }

    public function test_schedule_filters_apply_doctor_day_and_status_to_the_query(): void
    {
        $raka = $this->createDoctor(['name' => 'Raka Wirawan']);
        $sinta = $this->createDoctor(['name' => 'Sinta Maharani']);
        $this->createSchedule(['doctor_id' => $raka->id, 'clinic_id' => $raka->clinic_id, 'day_of_week' => 1]);
        $this->createSchedule(['doctor_id' => $raka->id, 'clinic_id' => $raka->clinic_id, 'day_of_week' => 2, 'is_active' => false]);
        $this->createSchedule(['doctor_id' => $sinta->id, 'clinic_id' => $sinta->clinic_id, 'day_of_week' => 1]);

        $apply = fn (array $query) => $this->resolve(DoctorScheduleIndexRequest::class, $query)
            ->applyTo(DoctorSchedule::query())
            ->get();

        $this->assertCount(3, $apply([]));
        $this->assertCount(2, $apply(['dokter' => $raka->slug]));
        $this->assertCount(2, $apply(['hari' => '1']));
        $this->assertCount(1, $apply(['dokter' => $raka->slug, 'hari' => '2', 'status' => 'nonaktif']));
        $this->assertCount(2, $apply(['status' => 'aktif']));
        $this->assertCount(3, $apply(['dokter' => 'tidak-ada']), 'Filter dokter yang tidak valid diabaikan.');
    }

    public function test_schedule_default_order_is_doctor_name_then_day_and_hari_sorts_by_day(): void
    {
        $sinta = $this->createDoctor(['name' => 'Sinta Maharani']);
        $raka = $this->createDoctor(['name' => 'Raka Wirawan']);
        $a = $this->createSchedule(['doctor_id' => $sinta->id, 'clinic_id' => $sinta->clinic_id, 'day_of_week' => 1]);
        $b = $this->createSchedule(['doctor_id' => $raka->id, 'clinic_id' => $raka->clinic_id, 'day_of_week' => 3]);
        $c = $this->createSchedule(['doctor_id' => $raka->id, 'clinic_id' => $raka->clinic_id, 'day_of_week' => 2]);

        $byDoctor = $this->resolve(DoctorScheduleIndexRequest::class, [])->applyTo(DoctorSchedule::query())->pluck('id')->all();
        $byDay = $this->resolve(DoctorScheduleIndexRequest::class, ['urut' => 'hari'])->applyTo(DoctorSchedule::query())->pluck('id')->all();
        $byDoctorDesc = $this->resolve(DoctorScheduleIndexRequest::class, ['urut' => '-dokter'])->applyTo(DoctorSchedule::query())->pluck('id')->all();

        $this->assertSame([$c->id, $b->id, $a->id], $byDoctor);
        $this->assertSame([$a->id, $c->id, $b->id], $byDay);
        $this->assertSame([$a->id, $c->id, $b->id], $byDoctorDesc);
    }

    // --- Pengecualian -----------------------------------------------------------------------------------

    public function test_exception_status_defaults_to_upcoming_and_invalid_values_are_ignored(): void
    {
        $this->assertSame('mendatang', $this->resolve(ScheduleExceptionIndexRequest::class, [])->statusFilter());
        $this->assertSame('mendatang', $this->resolve(ScheduleExceptionIndexRequest::class, ['status' => 'zzz'])->statusFilter());
        $this->assertSame('lampau', $this->resolve(ScheduleExceptionIndexRequest::class, ['status' => 'lampau'])->statusFilter());
        $this->assertSame('semua', $this->resolve(ScheduleExceptionIndexRequest::class, ['status' => 'semua'])->statusFilter());
    }

    public function test_exception_type_filter_ignores_unknown_types(): void
    {
        $this->assertSame(['jenis' => 'doctor_leave'], $this->resolve(ScheduleExceptionIndexRequest::class, ['jenis' => 'doctor_leave'])->filters());
        $this->assertSame([], $this->resolve(ScheduleExceptionIndexRequest::class, ['jenis' => 'libur-bebas'])->filters());
    }

    public function test_exception_query_splits_upcoming_and_past_by_end_date_and_filters_by_reason_and_type(): void
    {
        $past = $this->createException(['type' => 'other', 'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'reason' => 'Pelatihan lama']);
        $ongoing = $this->createException(['type' => 'other', 'start_date' => '2026-10-01', 'end_date' => '2026-10-05', 'reason' => 'Pelatihan berjalan']);
        $future = $this->createException(['type' => 'hospital_holiday', 'doctor_id' => null, 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'reason' => 'Libur_100% khusus']);

        $ids = fn (array $query) => $this->resolve(ScheduleExceptionIndexRequest::class, $query)
            ->applyTo(ScheduleException::query())
            ->pluck('id')
            ->all();

        $this->assertSame([$ongoing->id, $future->id], $ids([]));
        $this->assertSame([$past->id], $ids(['status' => 'lampau']));
        $this->assertSame([$past->id, $ongoing->id, $future->id], $ids(['status' => 'semua']));
        $this->assertSame([$future->id], $ids(['jenis' => 'hospital_holiday']));
        $this->assertSame([$ongoing->id], $ids(['q' => 'berjalan']));
        $this->assertSame([$future->id], $ids(['q' => '100%']), 'Karakter % dan _ pada kata kunci diperlakukan literal.');
        $this->assertSame([], $ids(['q' => '100_']), 'Garis bawah pada kata kunci tidak menjadi wildcard.');
    }

    // --- Sesi ------------------------------------------------------------------------------------------------

    public function test_session_range_defaults_to_today_plus_thirteen_days(): void
    {
        [$from, $to] = $this->resolve(PracticeSessionIndexRequest::class, [])->range();

        $this->assertSame('2026-10-05', $from->toDateString());
        $this->assertSame('2026-10-18', $to->toDateString());
    }

    public function test_session_range_uses_given_dates_and_ignores_an_end_before_the_start(): void
    {
        [$from, $to] = $this->resolve(PracticeSessionIndexRequest::class, ['dari' => '2026-11-01', 'sampai' => '2026-11-07'])->range();
        $this->assertSame(['2026-11-01', '2026-11-07'], [$from->toDateString(), $to->toDateString()]);

        [$from, $to] = $this->resolve(PracticeSessionIndexRequest::class, ['dari' => '2026-11-10'])->range();
        $this->assertSame(['2026-11-10', '2026-11-23'], [$from->toDateString(), $to->toDateString()]);

        $request = $this->resolve(PracticeSessionIndexRequest::class, ['dari' => '2026-11-10', 'sampai' => '2026-11-01']);
        [$from, $to] = $request->range();
        $this->assertSame(['dari' => '2026-11-10'], $request->filters());
        $this->assertSame(['2026-11-10', '2026-11-23'], [$from->toDateString(), $to->toDateString()]);
    }

    public function test_session_filters_ignore_invalid_values(): void
    {
        $request = $this->resolve(PracticeSessionIndexRequest::class, [
            'dari' => 'kemarin',
            'sampai' => '2026-02-31',
            'dokter' => 'tidak-ada',
            'poli' => ['x'],
            'status' => 'hancur',
        ]);

        $this->assertSame([], $request->filters());
        [$from, $to] = $request->range();
        $this->assertSame(['2026-10-05', '2026-10-18'], [$from->toDateString(), $to->toDateString()]);
    }

    public function test_session_query_applies_range_doctor_clinic_and_status_in_date_and_time_order(): void
    {
        $sintaClinic = $this->createClinic();
        $raka = $this->createDoctor(['name' => 'Raka Wirawan']);
        $sinta = $this->createDoctor(['name' => 'Sinta Maharani'], $sintaClinic);

        $late = $this->createSession(['doctor_id' => $raka->id, 'clinic_id' => $raka->clinic_id, 'session_date' => '2026-10-07', 'start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $early = $this->createSession(['doctor_id' => $raka->id, 'clinic_id' => $raka->clinic_id, 'session_date' => '2026-10-07', 'start_time' => '08:00:00', 'end_time' => '10:00:00']);
        $other = $this->createSession(['doctor_id' => $sinta->id, 'clinic_id' => $sinta->clinic_id, 'session_date' => '2026-10-06', 'start_time' => '09:00:00', 'end_time' => '11:00:00', 'status' => 'closed']);
        $outside = $this->createSession(['doctor_id' => $raka->id, 'clinic_id' => $raka->clinic_id, 'session_date' => '2026-10-19', 'start_time' => '09:00:00', 'end_time' => '11:00:00']);

        $ids = fn (array $query) => $this->resolve(PracticeSessionIndexRequest::class, $query)
            ->applyTo(PracticeSession::query())
            ->pluck('id')
            ->all();

        $this->assertSame([$other->id, $early->id, $late->id], $ids([]));
        $this->assertSame([$early->id, $late->id], $ids(['dokter' => $raka->slug]));
        $this->assertSame([$other->id], $ids(['poli' => $sintaClinic->slug]));
        $this->assertSame([$other->id], $ids(['status' => 'closed']));
        $this->assertSame([$outside->id], $ids(['dari' => '2026-10-19', 'sampai' => '2026-10-19']));
    }
}
