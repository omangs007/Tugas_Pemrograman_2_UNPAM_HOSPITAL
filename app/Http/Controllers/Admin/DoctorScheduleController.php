<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DoctorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DoctorScheduleIndexRequest;
use App\Http\Requests\Admin\DoctorScheduleRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Services\Scheduling\ScheduleChangeReport;
use App\Services\Scheduling\ScheduleChangeService;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Halaman #35, tab Templat Mingguan (F3-R19/R20/R22). Semua tulis lewat ScheduleChangeService:
 * simpan templat memicu pembangkitan sesi, propagasi (00 F.4), dan audit; flash memuat ringkasan dampak (F3-R23).
 */
class DoctorScheduleController extends Controller
{
    /** Hari ISO-8601 => nama (1 = Senin ... 7 = Minggu). */
    private const DAYS = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    public function index(DoctorScheduleIndexRequest $request): View
    {
        $this->authorize('viewAny', DoctorSchedule::class);

        $schedules = $request
            ->applyTo(DoctorSchedule::query()->with(['doctor', 'clinic']))
            ->paginate($request->perPage());

        return view('admin.schedules.index', [
            'schedules' => $schedules,
            'doctorOptions' => Doctor::query()->ordered()->get()
                ->mapWithKeys(fn (Doctor $doctor): array => [$doctor->slug => $doctor->display_name])
                ->all(),
            'dayOptions' => self::DAYS,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', DoctorSchedule::class);

        return view('admin.schedules.create', $this->formOptions(null));
    }

    public function store(DoctorScheduleRequest $request, ScheduleChangeService $service): RedirectResponse
    {
        $this->authorize('create', DoctorSchedule::class);

        $actor = Actor::staff($request->user());

        $report = DB::transaction(function () use ($request, $service, $actor): ScheduleChangeReport {
            $schedule = DoctorSchedule::query()->create($request->attributesForModel());

            return $service->applyTemplateCreated($schedule, $actor);
        });

        return redirect()->route('admin.schedules.index')
            ->with('success', $this->flash('messages.created', $report));
    }

    public function edit(DoctorSchedule $schedule): View
    {
        $this->authorize('update', $schedule);

        return view('admin.schedules.edit', ['schedule' => $schedule->load('doctor')] + $this->formOptions($schedule));
    }

    public function update(DoctorScheduleRequest $request, DoctorSchedule $schedule, ScheduleChangeService $service): RedirectResponse
    {
        $this->authorize('update', $schedule);

        $actor = Actor::staff($request->user());

        $report = DB::transaction(function () use ($request, $schedule, $service, $actor): ScheduleChangeReport {
            $schedule->fill($request->attributesForModel());

            // Nilai lama harus diambil sebelum save(): sesudahnya getOriginal() sudah berisi nilai baru.
            $before = $schedule->getOriginal();
            $schedule->save();

            return $service->applyTemplateChange($schedule, $before, $actor);
        });

        return redirect()->route('admin.schedules.index')
            ->with('success', $this->flash('messages.updated', $report));
    }

    /**
     * Penolakan (BR-12) datang dari service sebagai ValidationException, bukan Policy, sehingga berlaku juga
     * untuk Super Admin (F3-R20, Review Focus 5). Pesannya ditampilkan sebagai flash `error`.
     */
    public function destroy(Request $request, DoctorSchedule $schedule, ScheduleChangeService $service): RedirectResponse
    {
        $this->authorize('delete', $schedule);

        try {
            $service->deleteTemplate($schedule, Actor::staff($request->user()));
        } catch (ValidationException $exception) {
            return redirect()->route('admin.schedules.index')
                ->with('error', __('messages.delete_blocked', ['reason' => (string) collect($exception->errors())->flatten()->first()]));
        }

        return redirect()->route('admin.schedules.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.schedule')]));
    }

    /** Pesan baku ditambah ringkasan dampak (F3-R23) bila tidak kosong. */
    private function flash(string $key, ScheduleChangeReport $report): string
    {
        return trim(__($key, ['entity' => __('messages.entities.schedule')]).' '.$report->message());
    }

    /**
     * Pilihan form: dokter yang tidak diarsipkan (dokter nonaktif boleh, F3-R26) dan poliklinik aktif yang
     * dapat didaftar, ditambah poliklinik yang kini dipakai templat ini.
     *
     * @return array{doctorOptions: array<int, string>, clinicOptions: array<int, string>, dayOptions: array<int, string>}
     */
    private function formOptions(?DoctorSchedule $schedule): array
    {
        return [
            'doctorOptions' => Doctor::query()
                ->where('status', '!=', DoctorStatus::Archived->value)
                ->ordered()
                ->get()
                ->mapWithKeys(fn (Doctor $doctor): array => [
                    $doctor->id => $doctor->display_name.($doctor->status === DoctorStatus::Active ? '' : ' (nonaktif)'),
                ])
                ->all(),
            'clinicOptions' => Clinic::query()
                ->where(function ($query) use ($schedule): void {
                    $query->where(fn ($open) => $open->where('is_active', true)->where('is_bookable', true));

                    if ($schedule !== null) {
                        $query->orWhere('id', $schedule->clinic_id);
                    }
                })
                ->ordered()
                ->pluck('name', 'id')
                ->all(),
            'dayOptions' => self::DAYS,
        ];
    }
}
