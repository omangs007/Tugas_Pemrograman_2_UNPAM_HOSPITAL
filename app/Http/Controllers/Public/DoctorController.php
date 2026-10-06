<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\Availability;
use App\Enums\ClinicType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\DoctorIndexRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Page;
use App\Models\Specialty;
use App\Services\Scheduling\AvailabilityFilter;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Scheduling\SessionAvailability;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

/**
 * Halaman publik dokter (#6 `doctors.index`, #7 `doctors.show`). Jadwal dibaca hanya lewat
 * DoctorAvailabilityQuery (sumber yang sama dengan #8 dan wizard, JDW-12); pembangkitan sesi harian dipicu di sini
 * (F3-R9). Tanpa Policy: dokter yang tidak tampil di publik menjawab 404 untuk semua orang (F3-R17).
 */
class DoctorController extends Controller
{
    /** Jumlah kartu per halaman (07 W-03). */
    public const PER_PAGE = 12;

    public function index(
        DoctorIndexRequest $request,
        PracticeSessionGenerator $generator,
        DoctorAvailabilityQuery $query,
        SettingsService $settings,
    ): View {
        // Pemicu (b) pembangkitan sesi harian; kunci tidak menunggu, jadi halaman tetap tampil (F3-R8).
        $generator->ensureDailyHorizon();

        $filters = $request->filters();
        $errors = new MessageBag;

        if (($dateError = $request->dateError()) !== null) {
            $errors->add('tanggal', $dateError);
        }

        $specialties = Specialty::query()->active()->ordered()->get(['id', 'name', 'slug']);
        $clinics = Clinic::query()->active()->where('type', ClinicType::Poliklinik->value)->ordered()->get(['id', 'name', 'slug']);

        $specialty = null;

        if (isset($filters['spesialisasi'])) {
            $specialty = $specialties->firstWhere('slug', $filters['spesialisasi']);

            if ($specialty === null) {
                $errors->add('spesialisasi', 'Spesialisasi tidak dikenal. Filter ini diabaikan.');
                unset($filters['spesialisasi']);
            }
        }

        $clinic = null;

        if (isset($filters['poli'])) {
            $clinic = $clinics->firstWhere('slug', $filters['poli']);

            if ($clinic === null) {
                $errors->add('poli', 'Poliklinik tidak dikenal. Filter ini diabaikan.');
                unset($filters['poli']);
            }
        }

        $date = isset($filters['tanggal']) ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['tanggal']) : null;

        $doctors = $query->doctors(new AvailabilityFilter(
            clinicId: $clinic?->id,
            specialtyId: $specialty?->id,
            q: $filters['q'] ?? null,
            date: $date ?: null,
        ), self::PER_PAGE);

        $nextSessions = [];

        foreach ($doctors as $doctor) {
            // Data publik hanya memuat spesialisasi aktif (D-56); pivot `is_primary` tetap terbawa.
            $doctor->setRelation('specialties', $doctor->specialties->filter(fn (Specialty $item): bool => $item->is_active)->values());
            $nextSessions[$doctor->id] = $this->nextSession($doctor->availabilities, $date !== null);
        }

        // Galat inline dibagikan ke semua view (termasuk komponen `x-form.*`), bukan lewat `->withErrors()` yang hanya
        // terlihat oleh view induk dan tidak sampai ke komponen anonim (diverifikasi saat pemeriksaan konsistensi 2).
        view()->share('errors', (new ViewErrorBag)->put('default', $errors));

        return view('public.doctors.index', [
            'page' => Page::forKey('doctors-index'),
            'doctors' => $doctors,
            'nextSessions' => $nextSessions,
            'filters' => $filters,
            'specialtyOptions' => ['' => 'Semua spesialisasi'] + $specialties->pluck('name', 'slug')->all(),
            'clinicOptions' => ['' => 'Semua poliklinik'] + $clinics->pluck('name', 'slug')->all(),
            'windowDays' => max(1, $settings->bookingRules()->windowDays),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function show(
        Doctor $doctor,
        PracticeSessionGenerator $generator,
        DoctorAvailabilityQuery $query,
    ): View {
        // Tanpa Policy: dokter nonaktif, arsip, atau di poliklinik nonaktif = 404 untuk semua orang, termasuk staf (A-42).
        abort_unless(Doctor::query()->publiclyVisible()->whereKey($doctor->getKey())->exists(), 404);

        $generator->ensureDailyHorizon();

        $doctor->load([
            'clinic',
            'specialties' => fn ($specialties) => $specialties->where('specialties.is_active', true),
            'media' => fn ($media) => $media->where('collection', 'photo'),
        ]);

        // Jadwal 14 hari (hari ini sampai hari ini + 13) dari sumber yang sama dengan #8 dan wizard (JDW-12).
        $today = CarbonImmutable::today();
        $sessions = $query->sessions(new AvailabilityFilter(
            doctorId: $doctor->id,
            from: $today,
            to: $today->addDays(13),
        ));

        $specialty = $doctor->primary_specialty;
        $title = filled($doctor->meta_title)
            ? (string) $doctor->meta_title
            : $doctor->display_name.($specialty !== null ? ' — '.$specialty->name : '');

        return view('public.doctors.show', [
            'doctor' => $doctor,
            'sessions' => $sessions,
            'title' => $title,
            'metaDescription' => Str::limit((string) ($doctor->meta_description ?: $doctor->short_bio), 160, ''),
        ]);
    }

    /**
     * Sesi yang diwakili pada kartu: dengan filter tanggal, sesi pertama pada tanggal itu (termasuk Cuti/Libur agar
     * pengunjung tahu alasannya); tanpa filter, sesi terdekat yang bukan pembatalan (F3-R25).
     *
     * @param  Collection<int, SessionAvailability>  $availabilities
     */
    private function nextSession(Collection $availabilities, bool $forDate): ?SessionAvailability
    {
        if ($forDate) {
            return $availabilities->first();
        }

        $cancellations = [Availability::Leave, Availability::Holiday, Availability::Cancelled];

        return $availabilities->first(
            fn (SessionAvailability $item): bool => ! in_array($item->availability, $cancellations, true),
        );
    }
}
