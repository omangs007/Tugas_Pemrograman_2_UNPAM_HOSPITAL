<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DoctorStatus;
use App\Enums\ExceptionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleExceptionIndexRequest;
use App\Http\Requests\Admin\ScheduleExceptionRequest;
use App\Models\Doctor;
use App\Models\ScheduleException;
use App\Services\Scheduling\ScheduleChangeReport;
use App\Services\Scheduling\ScheduleChangeService;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman #35, tab Pengecualian (cuti dan libur; F3-R19/R21/R22). Simpan, ubah, dan hapus (= cabut) lewat
 * ScheduleChangeService: sesi dalam rentang dibatalkan atau ditandai perlu tindak lanjut, dan dibuka kembali
 * saat pengecualian dipersempit atau dicabut. Flash memuat ringkasan dampak (F3-R23).
 */
class ScheduleExceptionController extends Controller
{
    public function index(ScheduleExceptionIndexRequest $request): View
    {
        $this->authorize('viewAny', ScheduleException::class);

        $exceptions = $request
            ->applyTo(ScheduleException::query()->with('doctor'))
            ->paginate($request->perPage());

        return view('admin.exceptions.index', [
            'exceptions' => $exceptions,
            'statusFilter' => $request->statusFilter(),
            'typeOptions' => ExceptionType::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ScheduleException::class);

        return view('admin.exceptions.create', $this->formOptions());
    }

    public function store(ScheduleExceptionRequest $request, ScheduleChangeService $service): RedirectResponse
    {
        $this->authorize('create', ScheduleException::class);

        $actor = Actor::staff($request->user());

        $report = DB::transaction(function () use ($request, $service, $actor): ScheduleChangeReport {
            $exception = ScheduleException::query()->create($request->attributesForModel());

            return $service->applyException($exception, null, $actor);
        });

        return redirect()->route('admin.exceptions.index')
            ->with('success', $this->flash('messages.created', $report));
    }

    public function edit(ScheduleException $exception): View
    {
        $this->authorize('update', $exception);

        return view('admin.exceptions.edit', ['exception' => $exception] + $this->formOptions());
    }

    public function update(ScheduleExceptionRequest $request, ScheduleException $exception, ScheduleChangeService $service): RedirectResponse
    {
        $this->authorize('update', $exception);

        $actor = Actor::staff($request->user());

        $report = DB::transaction(function () use ($request, $exception, $service, $actor): ScheduleChangeReport {
            // Nilai lama (rentang dan cakupan dokter) dikirim ke service agar sesi yang terlepas dibuka kembali.
            $previous = [
                'start_date' => $exception->start_date,
                'end_date' => $exception->end_date,
                'doctor_id' => $exception->doctor_id === null ? null : (int) $exception->doctor_id,
            ];

            $exception->update($request->attributesForModel());

            return $service->applyException($exception, $previous, $actor);
        });

        return redirect()->route('admin.exceptions.index')
            ->with('success', $this->flash('messages.updated', $report));
    }

    /**
     * "Hapus" berarti mencabut pengecualian: removeException dipanggil SEBELUM baris dihapus, keduanya dalam
     * satu transaksi (00 C, 11 §6.6).
     */
    public function destroy(Request $request, ScheduleException $exception, ScheduleChangeService $service): RedirectResponse
    {
        $this->authorize('delete', $exception);

        $actor = Actor::staff($request->user());

        $report = DB::transaction(function () use ($exception, $service, $actor): ScheduleChangeReport {
            $report = $service->removeException($exception, $actor);
            $exception->delete();

            return $report;
        });

        return redirect()->route('admin.exceptions.index')
            ->with('success', $this->flash('messages.deleted', $report));
    }

    /** Pesan baku ditambah ringkasan dampak (F3-R23) bila tidak kosong. */
    private function flash(string $key, ScheduleChangeReport $report): string
    {
        return trim(__($key, ['entity' => __('messages.entities.exception')]).' '.$report->message());
    }

    /**
     * Pilihan form: jenis pengecualian dan dokter yang tidak diarsipkan. Baris pertama "Semua dokter" bernilai
     * kosong (libur rumah sakit atau libur nasional berlaku untuk semua dokter, 00 E.2).
     *
     * @return array{typeOptions: array<string, string>, doctorOptions: array<int|string, string>}
     */
    private function formOptions(): array
    {
        $doctors = Doctor::query()
            ->where('status', '!=', DoctorStatus::Archived->value)
            ->ordered()
            ->get()
            ->mapWithKeys(fn (Doctor $doctor): array => [
                $doctor->id => $doctor->display_name.($doctor->status === DoctorStatus::Active ? '' : ' (nonaktif)'),
            ])
            ->all();

        return [
            'typeOptions' => ExceptionType::options(),
            'doctorOptions' => ['' => 'Semua dokter'] + $doctors,
        ];
    }
}
