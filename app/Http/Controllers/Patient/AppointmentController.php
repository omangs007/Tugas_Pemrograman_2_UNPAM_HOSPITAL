<?php

namespace App\Http\Controllers\Patient;

use App\Enums\Availability;
use App\Enums\ClinicType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\BookingWizardQueryRequest;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\InsurancePartner;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Services\Scheduling\AvailabilityFilter;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Scheduling\SessionAvailability;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Pendaftaran kunjungan pasien (#29, F6-R17): langkah 1 sampai 4 adalah satu GET `create` (query `poli`, `dokter`,
 * `tanggal`, `sesi`); langkah 5 (tinjau) dan simpan menyusul di T8. Sumber jadwal SATU-SATUNYA adalah
 * DoctorAvailabilityQuery (JDW-12); aturan F.5 lengkap tetap di precheck/book.
 */
class AppointmentController extends Controller
{
    /** Sesi yang dibatalkan alasan operasional tidak layak menjadi "praktik terdekat". */
    private const CANCELLATIONS = [Availability::Leave, Availability::Holiday, Availability::Cancelled];

    public function create(
        BookingWizardQueryRequest $request,
        PracticeSessionGenerator $generator,
        DoctorAvailabilityQuery $availability,
        SettingsService $settings,
    ): View {
        $this->authorize('createOnline', Appointment::class);

        $generator->ensureDailyHorizon();

        $profile = $request->user()->loadMissing('patientProfile')->patientProfile;
        $filters = $request->filters();
        $rules = $settings->bookingRules();

        $data = match (true) {
            $filters['sesi'] !== null => $this->stepFromSession($availability, $profile, (int) $filters['sesi']),
            $filters['poli'] !== null && $filters['dokter'] !== null => $this->stepSessions(
                $availability,
                $this->clinicBySlug($filters['poli']),
                $this->doctorBySlug($filters['dokter']),
                $filters['tanggal'],
            ),
            $filters['poli'] !== null => $this->stepDoctors($availability, $this->clinicBySlug($filters['poli'])),
            default => $this->stepClinics($availability),
        };

        return view('patient.appointments.create', $data + [
            'windowDays' => max(1, $rules->windowDays),
            'cutoffMinutes' => $rules->onlineCutoffMinutes,
        ]);
    }

    // [[METODE-PUBLIK-BERIKUT]] (T8 sampai T10 menambah method publik di atas baris ini: review, store, show, index, cancel, proof, proofPdf)

    // --- langkah wizard ----------------------------------------------------------------------

    /** Langkah 1: poliklinik poliklinik yang aktif dan dapat didaftar online; redup bila tanpa sesi di jendela. */
    private function stepClinics(DoctorAvailabilityQuery $availability): array
    {
        $clinics = Clinic::query()
            ->bookable()
            ->where('type', ClinicType::Poliklinik->value)
            ->ordered()
            ->get(['id', 'name', 'slug', 'code', 'location', 'sort_order']);

        $withSessions = $availability->sessions(new AvailabilityFilter)
            ->reject(fn (SessionAvailability $item): bool => in_array($item->availability, self::CANCELLATIONS, true))
            ->map(fn (SessionAvailability $item): int => (int) $item->session->clinic_id)
            ->unique()
            ->all();

        return [
            'step' => 1,
            'links' => [],
            'clinicRows' => $clinics->map(fn (Clinic $clinic): array => [
                'clinic' => $clinic,
                'hasSessions' => in_array((int) $clinic->id, $withSessions, true),
            ]),
        ];
    }

    /** Langkah 2: dokter aktif poliklinik itu (sesi di poliklinik itu, ditambah dokter poliklinik tanpa sesi). */
    private function stepDoctors(DoctorAvailabilityQuery $availability, Clinic $clinic): array
    {
        $items = $availability->sessions(new AvailabilityFilter(clinicId: $clinic->id));
        $grouped = $items->groupBy(fn (SessionAvailability $item): int => (int) $item->session->doctor_id);

        $doctors = $items
            ->map(fn (SessionAvailability $item): Doctor => $item->session->doctor)
            ->unique('id')
            ->keyBy('id');

        Doctor::query()->publiclyVisible()->inClinic($clinic->id)->with(['specialties', 'media'])->get()
            ->each(function (Doctor $doctor) use ($doctors): void {
                if (! $doctors->has($doctor->id)) {
                    $doctors->put($doctor->id, $doctor);
                }
            });

        $rows = $doctors
            ->map(function (Doctor $doctor) use ($grouped): array {
                // Hanya spesialisasi aktif yang tampil di publik (E12, D-56).
                $doctor->setRelation('specialties', $doctor->specialties->filter(fn ($specialty): bool => (bool) $specialty->is_active)->values());
                $own = $grouped->get($doctor->id, new Collection);

                return [
                    'doctor' => $doctor,
                    'bookable' => $own->first(fn (SessionAvailability $item): bool => $item->isBookableOnline),
                    'next' => $own->first(fn (SessionAvailability $item): bool => ! in_array($item->availability, self::CANCELLATIONS, true)),
                ];
            })
            ->sort(fn (array $a, array $b): int => [$a['doctor']->sort_order, $a['doctor']->name] <=> [$b['doctor']->sort_order, $b['doctor']->name])
            ->values();

        return [
            'step' => 2,
            'links' => $this->stepLinks(),
            'clinic' => $clinic,
            'doctorRows' => $rows,
        ];
    }

    /** Langkah 3: chip tanggal (jendela pendaftaran) dan sesi pada tanggal terpilih. */
    private function stepSessions(DoctorAvailabilityQuery $availability, Clinic $clinic, Doctor $doctor, ?string $date): array
    {
        $items = $availability->sessions(new AvailabilityFilter(clinicId: $clinic->id, doctorId: $doctor->id));

        $entries = $items
            ->groupBy(fn (SessionAvailability $item): string => $item->session->session_date->toDateString())
            ->map(function (Collection $group, string $key): array {
                $selectable = $group->contains(fn (SessionAvailability $item): bool => $item->isBookableOnline);

                return [
                    'key' => $key,
                    'date' => CarbonImmutable::createFromFormat('!Y-m-d', $key),
                    'items' => $group->values(),
                    'selectable' => $selectable,
                    'reason' => $selectable ? null : $group->first()->availability,
                ];
            });

        $selected = $date !== null && ($entries->get($date)['selectable'] ?? false)
            ? $entries->get($date)
            : $entries->firstWhere('selectable', true);

        return [
            'step' => 3,
            'links' => $this->stepLinks($clinic),
            'clinic' => $clinic,
            'doctor' => $doctor,
            'dateEntries' => $entries->values(),
            'selected' => $selected,
        ];
    }

    /**
     * `?sesi=` langsung (alur 3.C, F6-R18): sesi yang dapat dipesan online langsung ke langkah 4; selain itu langkah 3
     * untuk dokter itu dengan peringatan berisi label ketersediaan.
     */
    private function stepFromSession(DoctorAvailabilityQuery $availability, PatientProfile $profile, int $sessionId): array
    {
        $session = PracticeSession::query()->with(['clinic', 'doctor', 'exception'])->findOrFail($sessionId);
        $item = $availability->forSession($session);

        if (! $item->isBookableOnline) {
            session()->now('warning', 'Sesi yang dipilih tidak dapat dipesan: '.$item->availability->label().'.');

            return $this->stepSessions($availability, $session->clinic, $session->doctor, $session->session_date->toDateString());
        }

        return [
            'step' => 4,
            'links' => $this->stepLinks($session->clinic, $session->doctor, $session->session_date->toDateString()),
            'session' => $session,
            'item' => $item,
            'clinic' => $session->clinic,
            'doctor' => $session->doctor,
            'profile' => $profile,
            'partners' => InsurancePartner::query()->active()->ordered()->get(['id', 'name']),
            'storedBpjs' => $profile->masked_bpjs,
        ];
    }

    private function clinicBySlug(string $slug): Clinic
    {
        return Clinic::query()->bookable()->where('type', ClinicType::Poliklinik->value)->where('slug', $slug)->firstOrFail();
    }

    private function doctorBySlug(string $slug): Doctor
    {
        return Doctor::query()->active()->where('slug', $slug)->firstOrFail();
    }

    /**
     * Tautan langkah yang sudah dilewati untuk x-wizard-steps (hanya yang kurang dari langkah aktif yang dirender).
     *
     * @return array<int, string>
     */
    private function stepLinks(?Clinic $clinic = null, ?Doctor $doctor = null, ?string $date = null, ?int $sessionId = null): array
    {
        $links = [1 => route('patient.appointments.create')];

        if ($clinic !== null) {
            $links[2] = route('patient.appointments.create', ['poli' => $clinic->slug]);
        }

        if ($clinic !== null && $doctor !== null) {
            $links[3] = route('patient.appointments.create', array_filter(['poli' => $clinic->slug, 'dokter' => $doctor->slug, 'tanggal' => $date]));
        }

        if ($sessionId !== null) {
            $links[4] = route('patient.appointments.create', ['sesi' => $sessionId]);
        }

        return $links;
    }
}
