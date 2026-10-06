<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\ClinicType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ScheduleIndexRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Page;
use App\Services\Scheduling\AvailabilityFilter;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Scheduling\SessionAvailability;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

/**
 * Halaman #8 `schedules.index` (05 hal. 8, 07 W-05, F3-R24): jadwal satu minggu dari DoctorAvailabilityQuery, sumber
 * yang sama dengan #7 dan wizard (JDW-12). Dua tata letak dari koleksi yang sama: grid mingguan (layar >= md) dan
 * daftar bertumpuk per hari (layar kecil). Pembangkitan sesi harian dipicu di sini (F3-R9).
 */
class ScheduleController extends Controller
{
    public function index(
        ScheduleIndexRequest $request,
        PracticeSessionGenerator $generator,
        DoctorAvailabilityQuery $query,
        SettingsService $settings,
    ): View {
        $generator->ensureDailyHorizon();

        $today = CarbonImmutable::today();
        $lastDay = $today->addDays(max(1, $settings->bookingRules()->windowDays));
        $firstWeek = $today->startOfWeek(CarbonInterface::MONDAY);
        $lastWeek = $lastDay->startOfWeek(CarbonInterface::MONDAY);

        $filters = $request->filters();
        $notice = $request->weekError();
        $weekStart = $firstWeek;

        // `minggu` dinormalisasi ke Senin; di luar [minggu ini, minggu yang memuat hari terakhir jendela]
        // diganti minggu terdekat yang valid disertai pesan (F3-R24).
        if (isset($filters['minggu'])) {
            $requested = CarbonImmutable::createFromFormat('!Y-m-d', $filters['minggu'])->startOfWeek(CarbonInterface::MONDAY);

            if ($requested->lessThan($firstWeek)) {
                $notice = 'Menampilkan minggu ini.';
            } elseif ($requested->greaterThan($lastWeek)) {
                $weekStart = $lastWeek;
                $notice = 'Jadwal hanya tersedia sampai '.$lastDay->translatedFormat('j F Y').'.';
            } else {
                $weekStart = $requested;
            }
        }

        $weekEnd = $weekStart->addDays(6);

        $clinics = Clinic::query()->active()->where('type', ClinicType::Poliklinik->value)->ordered()->get(['id', 'name', 'slug']);
        $doctors = Doctor::query()->publiclyVisible()->ordered()->get(['id', 'name', 'slug', 'title_prefix', 'title_suffix']);
        $errors = new MessageBag;

        $clinic = null;

        if (isset($filters['poli'])) {
            $clinic = $clinics->firstWhere('slug', $filters['poli']);

            if ($clinic === null) {
                $errors->add('poli', 'Poliklinik tidak dikenal. Filter ini diabaikan.');
                unset($filters['poli']);
            }
        }

        $doctor = null;

        if (isset($filters['dokter'])) {
            $doctor = $doctors->firstWhere('slug', $filters['dokter']);

            if ($doctor === null) {
                $errors->add('dokter', 'Dokter tidak dikenal. Filter ini diabaikan.');
                unset($filters['dokter']);
            }
        }

        $items = $query->sessions(new AvailabilityFilter(
            clinicId: $clinic?->id,
            doctorId: $doctor?->id,
            from: $weekStart,
            to: $weekEnd,
            includePast: true,
        ));

        $days = collect(range(0, 6))->map(fn (int $offset): CarbonImmutable => $weekStart->addDays($offset));
        $byDate = $items->groupBy(fn (SessionAvailability $item): string => $item->session->session_date->toDateString());

        // Baris grid: satu baris per dokter, urut sort_order lalu nama; sel = sesi dokter itu pada tanggal tersebut.
        $rows = $items
            ->groupBy(fn (SessionAvailability $item): int => (int) $item->session->doctor_id)
            ->map(fn (Collection $group): array => [
                'doctor' => $group->first()->session->doctor,
                'cells' => $group->groupBy(fn (SessionAvailability $item): string => $item->session->session_date->toDateString()),
            ])
            ->sortBy(fn (array $row): string => sprintf('%05d|%s', $row['doctor']->sort_order, $row['doctor']->name))
            ->values();

        // Galat inline dibagikan ke semua view agar `x-form.select` melihatnya (lihat DoctorController@index).
        view()->share('errors', (new ViewErrorBag)->put('default', $errors));

        return view('public.schedules.index', [
            'page' => Page::forKey('schedules-index'),
            'items' => $items,
            'days' => $days,
            'byDate' => $byDate,
            'rows' => $rows,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekLabel' => $weekStart->translatedFormat('j F').' – '.$weekEnd->translatedFormat('j F Y'),
            'previousWeek' => $weekStart->greaterThan($firstWeek) ? $weekStart->subWeek() : null,
            'nextWeek' => $weekStart->lessThan($lastWeek) ? $weekStart->addWeek() : null,
            'notice' => $notice,
            'filters' => $filters,
            'clinicOptions' => ['' => 'Semua poliklinik'] + $clinics->pluck('name', 'slug')->all(),
            'doctorOptions' => ['' => 'Semua dokter'] + $doctors->mapWithKeys(fn (Doctor $row): array => [$row->slug => $row->display_name])->all(),
        ]);
    }
}
