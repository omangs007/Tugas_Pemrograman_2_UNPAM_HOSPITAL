<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\Availability;
use App\Enums\DoctorStatus;
use App\Enums\SessionStatus;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Models\Specialty;
use App\Services\Booking\BookingRules;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Satu-satunya sumber jadwal untuk halaman publik, wizard pasien, dan form walk-in (00 F.3, BR-16).
 * Hanya membaca; klasifikasi `Availability` mengikuti urutan 11 §6.5 (F3-R12).
 */
final class DoctorAvailabilityQuery
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Sesi dokter yang tampil di publik (F3-R17) beserta labelnya, urut tanggal lalu jam mulai.
     * Sesi `past` dibuang kecuali `includePast`.
     *
     * @return Collection<int, SessionAvailability>
     */
    public function sessions(AvailabilityFilter $filter, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();
        $rules = $this->settings->bookingRules();

        $items = $this->describeAll($this->sessionQuery($filter, $rules, $now)->get(), $rules, $now, $filter->forLoket);

        if (! $filter->includePast) {
            $items = $this->withoutPast($items);
        }

        return $items->values();
    }

    /**
     * Label satu sesi lewat kode yang sama dengan sessions() (kontrak JDW-12). Relasi yang dibutuhkan
     * klasifikasi dimuat bila belum.
     */
    public function forSession(PracticeSession $session, bool $forLoket = false, ?CarbonImmutable $now = null): SessionAvailability
    {
        $session->loadMissing(['doctor', 'clinic', 'exception']);

        return $this->describe($session, $this->settings->bookingRules(), $now ?? CarbonImmutable::now(), $forLoket);
    }

    /**
     * Direktori dokter (halaman #6). Setiap dokter pada halaman diberi relasi `availabilities`
     * (Collection<SessionAvailability>) lewat satu kueri sesi per halaman: sesi pada `date` bila diisi
     * (termasuk sesi Cuti/Libur), selain itu sesi hari ini … + jendela yang belum lewat.
     */
    public function doctors(AvailabilityFilter $filter, int $perPage = 12): LengthAwarePaginator
    {
        $page = Doctor::query()
            ->publiclyVisible()
            ->when($filter->q !== null && trim($filter->q) !== '', fn (Builder $query) => $query->search($filter->q))
            ->when($filter->specialtyId !== null, fn (Builder $query) => $query->inSpecialty((int) $filter->specialtyId))
            ->when($filter->clinicId !== null, fn (Builder $query) => $query->inClinic((int) $filter->clinicId))
            ->when($filter->date !== null, fn (Builder $query) => $query->whereHas(
                'sessions',
                fn (Builder $sessions) => $sessions->where('session_date', $filter->date->toDateString()),
            ))
            ->with(['clinic', 'specialties', 'media'])
            ->ordered()
            ->paginate($perPage);

        $doctors = $page->getCollection();

        if ($doctors->isEmpty()) {
            return $page;
        }

        $now = CarbonImmutable::now();
        $rules = $this->settings->bookingRules();
        [$from, $to] = $this->range($filter, $rules, $now);

        $sessions = PracticeSession::query()
            ->with(['clinic', 'exception'])
            ->whereIn('doctor_id', $doctors->modelKeys())
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $byId = $doctors->keyBy('id');
        $sessions->each(fn (PracticeSession $session) => $session->setRelation('doctor', $byId->get($session->doctor_id)));

        $items = $this->describeAll($sessions, $rules, $now, $filter->forLoket);

        if ($filter->date === null && ! $filter->includePast) {
            $items = $this->withoutPast($items);
        }

        $grouped = $items->groupBy(fn (SessionAvailability $item): int => (int) $item->session->doctor_id);

        foreach ($doctors as $doctor) {
            $doctor->setRelation('availabilities', ($grouped->get($doctor->getKey()) ?? new Collection)->values());
        }

        return $page;
    }

    /**
     * Menyiapkan dokter hasil `doctors()` untuk kartu publik (beranda dan #6; carry E12 fase 4a, F6-R32): relasi
     * `specialties` diganti dengan spesialisasi AKTIF saja (D-56; pivot `is_primary` tetap terbawa) dan mengembalikan
     * sesi yang diwakili kartu tiap dokter, berkunci id dokter (`null` bila tidak ada). Dengan `$forDate` (filter
     * tanggal #6) sesi yang diwakili ialah sesi pertama pada tanggal itu, termasuk Cuti/Libur agar pengunjung tahu
     * alasannya; tanpa itu, sesi terdekat yang bukan pembatalan (F3-R25). Relasi `specialties` dan `availabilities`
     * wajib sudah dimuat; tidak ada kueri.
     *
     * @param  iterable<int, Doctor>  $doctors
     * @return array<int, SessionAvailability|null>
     */
    public function prepareCards(iterable $doctors, bool $forDate = false): array
    {
        $sessions = [];

        foreach ($doctors as $doctor) {
            // Data publik hanya memuat spesialisasi aktif (D-56); pivot `is_primary` tetap terbawa.
            $doctor->setRelation('specialties', $doctor->specialties->filter(fn (Specialty $item): bool => $item->is_active)->values());
            $sessions[$doctor->id] = $this->representativeSession($doctor->availabilities, $forDate);
        }

        return $sessions;
    }

    /**
     * @param  Collection<int, SessionAvailability>  $availabilities
     */
    private function representativeSession(Collection $availabilities, bool $forDate): ?SessionAvailability
    {
        if ($forDate) {
            return $availabilities->first();
        }

        $cancellations = [Availability::Leave, Availability::Holiday, Availability::Cancelled];

        return $availabilities->first(
            fn (SessionAvailability $item): bool => ! in_array($item->availability, $cancellations, true),
        );
    }

    /**
     * Klasifikasi murni (tanpa kueri) menurut F3-R12; yang pertama cocok dipakai. Relasi `doctor`, `clinic`,
     * dan `exception` pada sesi wajib sudah dimuat (mode ketat).
     */
    public function classify(PracticeSession $session, BookingRules $rules, CarbonImmutable $now, bool $forLoket): Availability
    {
        $startsAt = CarbonImmutable::instance($session->starts_at);
        $endsAt = CarbonImmutable::instance($session->ends_at);

        if ($now->greaterThanOrEqualTo($endsAt)) {
            return Availability::Past;
        }

        if ($session->status === SessionStatus::Cancelled) {
            return Availability::forException($session->exception?->type);
        }

        if ($session->status === SessionStatus::NeedsReview) {
            return Availability::UnderReview;
        }

        if (
            $session->status === SessionStatus::Closed
            || $session->doctor->status !== DoctorStatus::Active
            || ! $session->clinic->is_active
        ) {
            return Availability::Closed;
        }

        if ($session->quota - $session->booked_count <= 0) {
            return Availability::Full;
        }

        $beyondWindow = $session->session_date->toDateString() > $now->startOfDay()->addDays($rules->windowDays)->toDateString();
        $clinicNotBookable = ! $session->clinic->is_bookable;

        if ($beyondWindow || $clinicNotBookable) {
            return Availability::BookingClosed;
        }

        if (! $forLoket && $now->greaterThanOrEqualTo($startsAt->subMinutes($rules->onlineCutoffMinutes))) {
            return Availability::BookingClosed;
        }

        return Availability::Available;
    }

    private function describe(PracticeSession $session, BookingRules $rules, CarbonImmutable $now, bool $forLoket): SessionAvailability
    {
        return new SessionAvailability(
            session: $session,
            remaining: max(0, $session->quota - $session->booked_count),
            availability: $this->classify($session, $rules, $now, $forLoket),
            isBookableOnline: $this->classify($session, $rules, $now, false) === Availability::Available,
            isBookableCounter: $this->classify($session, $rules, $now, true) === Availability::Available,
        );
    }

    /**
     * @param  Collection<int, PracticeSession>  $sessions
     * @return Collection<int, SessionAvailability>
     */
    private function describeAll(Collection $sessions, BookingRules $rules, CarbonImmutable $now, bool $forLoket): Collection
    {
        return $sessions
            ->map(fn (PracticeSession $session): SessionAvailability => $this->describe($session, $rules, $now, $forLoket))
            ->values();
    }

    /**
     * @param  Collection<int, SessionAvailability>  $items
     * @return Collection<int, SessionAvailability>
     */
    private function withoutPast(Collection $items): Collection
    {
        return $items->reject(fn (SessionAvailability $item): bool => $item->availability === Availability::Past);
    }

    /**
     * @return Builder<PracticeSession>
     */
    private function sessionQuery(AvailabilityFilter $filter, BookingRules $rules, CarbonImmutable $now): Builder
    {
        [$from, $to] = $this->range($filter, $rules, $now);

        return PracticeSession::query()
            ->with(['doctor.specialties', 'doctor.media', 'clinic', 'exception'])
            ->whereHas('doctor', fn (Builder $doctor) => $doctor->publiclyVisible())
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->when($filter->clinicId !== null, fn (Builder $query) => $query->where('clinic_id', $filter->clinicId))
            ->when($filter->doctorId !== null, fn (Builder $query) => $query->where('doctor_id', $filter->doctorId))
            ->when($filter->specialtyId !== null, fn (Builder $query) => $query->whereHas(
                'doctor.specialties',
                fn (Builder $specialties) => $specialties->where('specialties.id', $filter->specialtyId),
            ))
            ->when($filter->q !== null && trim($filter->q) !== '', fn (Builder $query) => $query->whereHas(
                'doctor',
                fn (Builder $doctor) => $doctor->search($filter->q),
            ))
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->orderBy('id');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(AvailabilityFilter $filter, BookingRules $rules, CarbonImmutable $now): array
    {
        if ($filter->date !== null) {
            return [$filter->date, $filter->date];
        }

        return [
            $filter->from ?? $now->startOfDay(),
            $filter->to ?? $now->startOfDay()->addDays($rules->windowDays),
        ];
    }
}
