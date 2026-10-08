<?php

namespace Tests\Feature\Scheduling;

use App\Enums\Availability;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Services\Scheduling\AvailabilityFilter;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\SessionAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Carry E12 (F6-R32): `DoctorAvailabilityQuery::prepareCards()` = satu definisi sesi yang diwakili kartu dokter publik
 * dan satu saringan spesialisasi aktif. Sekarang = Senin 2026-10-05 07.00.
 */
class DoctorCardPreparationTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 07:00:00'); // Senin
    }

    private function query(): DoctorAvailabilityQuery
    {
        return $this->app->make(DoctorAvailabilityQuery::class);
    }

    /** Direktori dokter halaman pertama, seperti yang dipakai controller publik. */
    private function directory(?CarbonImmutable $date = null): LengthAwarePaginator
    {
        request()->query->remove('page');

        return $this->query()->doctors(new AvailabilityFilter(date: $date), 12);
    }

    /** @param  array<string, mixed>  $overrides */
    private function sessionOf(Doctor $doctor, string $date, array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge([
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ], $overrides));
    }

    private function leaveSession(Doctor $doctor, string $date): PracticeSession
    {
        $exception = $this->createException(['doctor_id' => $doctor->id, 'type' => ExceptionType::DoctorLeave, 'start_date' => $date, 'end_date' => $date]);

        return $this->sessionOf($doctor, $date, ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $exception->id]);
    }

    /**
     * @param  array<int, SessionAvailability|null>  $cards
     * @return array<int, int|null>
     */
    private function sessionIds(array $cards): array
    {
        return array_map(fn (?SessionAvailability $card): ?int => $card?->session->id, $cards);
    }

    public function test_it_returns_the_nearest_session_that_is_not_a_cancellation_keyed_by_doctor_id(): void
    {
        $doctor = $this->createDoctor();
        $withoutSessions = $this->createDoctor();
        $this->leaveSession($doctor, '2026-10-06');
        $this->sessionOf($doctor, '2026-10-07', ['status' => SessionStatus::Cancelled]);
        $nearest = $this->sessionOf($doctor, '2026-10-08');
        $this->sessionOf($doctor, '2026-10-09');

        $cards = $this->query()->prepareCards($this->directory());

        $this->assertEqualsCanonicalizing([$doctor->id, $withoutSessions->id], array_keys($cards));
        $this->assertSame($nearest->id, $cards[$doctor->id]->session->id);
        $this->assertSame(Availability::Available, $cards[$doctor->id]->availability);
        $this->assertNull($cards[$withoutSessions->id]);
    }

    public function test_a_holiday_or_a_plain_cancellation_never_stands_in_for_the_nearest_session(): void
    {
        $doctor = $this->createDoctor();
        $holiday = $this->createException(['doctor_id' => null, 'type' => ExceptionType::NationalHoliday, 'start_date' => '2026-10-06', 'end_date' => '2026-10-06']);
        $this->sessionOf($doctor, '2026-10-06', ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $holiday->id]);
        $this->sessionOf($doctor, '2026-10-07', ['status' => SessionStatus::Cancelled]);

        $cards = $this->query()->prepareCards($this->directory());

        $this->assertNull($cards[$doctor->id]);
    }

    public function test_a_full_session_still_counts_as_the_nearest_session(): void
    {
        $doctor = $this->createDoctor();
        $full = $this->sessionOf($doctor, '2026-10-06', ['quota' => 1, 'booked_count' => 1, 'last_queue_number' => 1]);

        $cards = $this->query()->prepareCards($this->directory());

        $this->assertSame($full->id, $cards[$doctor->id]->session->id);
        $this->assertSame(Availability::Full, $cards[$doctor->id]->availability);
    }

    public function test_with_a_date_it_returns_the_first_session_of_the_day_even_when_it_is_a_leave(): void
    {
        $doctor = $this->createDoctor();
        $leave = $this->leaveSession($doctor, '2026-10-06');
        $this->sessionOf($doctor, '2026-10-06', ['start_time' => '13:00:00', 'end_time' => '15:00:00']);

        $cards = $this->query()->prepareCards($this->directory(CarbonImmutable::parse('2026-10-06')), true);

        $this->assertSame($leave->id, $cards[$doctor->id]->session->id);
        $this->assertSame(Availability::Leave, $cards[$doctor->id]->availability);
    }

    public function test_it_keeps_only_active_specialties_and_preserves_the_pivot(): void
    {
        $hidden = $this->createSpecialty(['name' => 'Spesialis Nonaktif Utama', 'is_active' => false]);
        $shown = $this->createSpecialty(['name' => 'Spesialis Aktif Tambahan']);
        $doctor = $this->createDoctor([], null, [$hidden, $shown]);

        $directory = $this->directory();
        $this->query()->prepareCards($directory);

        $prepared = collect($directory->items())->firstWhere('id', $doctor->id);

        $this->assertSame(['Spesialis Aktif Tambahan'], $prepared->specialties->pluck('name')->all());
        $this->assertFalse((bool) $prepared->specialties->first()->pivot->is_primary, 'Pivot asli (bukan primer) tetap terbawa.');
        $this->assertSame('Spesialis Aktif Tambahan', $prepared->primary_specialty->name);
    }

    public function test_it_accepts_a_paginator_and_a_plain_collection_with_the_same_result(): void
    {
        $doctor = $this->createDoctor();
        $this->sessionOf($doctor, '2026-10-08');
        $this->createDoctor();

        $fromPaginator = $this->query()->prepareCards($this->directory());
        $fromCollection = $this->query()->prepareCards($this->directory()->getCollection());

        $this->assertSame($this->sessionIds($fromPaginator), $this->sessionIds($fromCollection));
        $this->assertCount(2, $fromPaginator);
    }

    public function test_an_empty_list_gives_an_empty_map(): void
    {
        $this->assertSame([], $this->query()->prepareCards([]));
        $this->assertSame([], $this->query()->prepareCards($this->directory()));
    }

    /** Mode ketat: `specialties` dan `availabilities` sudah dimuat `doctors()`; menyiapkan kartu tidak boleh mengueri. */
    public function test_preparing_cards_makes_no_queries(): void
    {
        $doctor = $this->createDoctor();
        $this->sessionOf($doctor, '2026-10-08');
        $directory = $this->directory();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->query()->prepareCards($directory);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries);
    }
}
