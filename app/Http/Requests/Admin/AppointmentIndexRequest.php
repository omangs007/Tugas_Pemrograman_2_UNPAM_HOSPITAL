<?php

namespace App\Http\Requests\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Http\Requests\ListFilterRequest;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Services\Booking\BookingCodeGenerator;
use App\Services\Patients\PatientSearch;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Filter daftar pendaftaran #38 (F7A-R17, 12 V-46): `dari`/`sampai` (tanggal kunjungan; bila keduanya kosong, hari ini
 * sampai hari ini), `poli` dan `dokter` (slug), `status` (AppointmentStatus), `kanal` (BookingChannel), `q` (kode booking,
 * nomor pasien, atau nama). Nilai tidak valid atau larik diabaikan (pola ListFilterRequest). Urutan tetap: tanggal kunjungan,
 * jam mulai sesi, sesi, nomor antrean.
 */
class AppointmentIndexRequest extends ListFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'poli' => ['nullable', 'string', 'max:150', Rule::exists('clinics', 'slug')],
            'dokter' => ['nullable', 'string', 'max:150', Rule::exists('doctors', 'slug')],
            'kanal' => ['nullable', Rule::in(BookingChannel::values())],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'dari.date_format' => 'Format tanggal tidak valid.',
            'sampai.date_format' => 'Format tanggal tidak valid.',
            'sampai.after_or_equal' => 'Tanggal sampai tidak boleh sebelum tanggal dari.',
            'poli.string' => 'Pilihan filter tidak valid.',
            'poli.max' => 'Pilihan filter tidak valid.',
            'poli.exists' => 'Pilihan filter tidak valid.',
            'dokter.string' => 'Pilihan filter tidak valid.',
            'dokter.max' => 'Pilihan filter tidak valid.',
            'dokter.exists' => 'Pilihan filter tidak valid.',
            'kanal.in' => 'Kanal tidak valid.',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return AppointmentStatus::values();
    }

    /**
     * Rentang terbalik: hanya `sampai` yang gagal dan diabaikan, `dari` tetap berlaku (pola ListFilterRequest, yang
     * tidak diubah). Pesan menyebut persis apa yang diabaikan; kegagalan lain memakai pesan umum.
     */
    protected function failedValidation(Validator $validator): void
    {
        $failed = $validator->failed();

        if (array_keys($failed) === ['sampai'] && array_keys($failed['sampai']) === ['AfterOrEqual']) {
            session()->now('warning', 'Tanggal sampai tidak boleh sebelum tanggal dari: batas sampai diabaikan, tanggal dari tetap berlaku.');

            return;
        }

        parent::failedValidation($validator);
    }

    /**
     * Rentang tanggal kunjungan yang berlaku. Tanpa `dari` dan `sampai`: hari ini sampai hari ini. Bila hanya satu yang
     * ada, sisi lainnya terbuka.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public function range(): array
    {
        $filters = $this->filters();

        $from = isset($filters['dari']) ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['dari']) : null;
        $to = isset($filters['sampai']) ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['sampai']) : null;

        if ($from === null && $to === null) {
            $today = CarbonImmutable::today();

            return [$today, $today];
        }

        return [$from ?: null, $to ?: null];
    }

    /**
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        [$from, $to] = $this->range();

        if ($from !== null) {
            $query->where('appointments.session_date', '>=', $from->toDateString());
        }

        if ($to !== null) {
            $query->where('appointments.session_date', '<=', $to->toDateString());
        }

        if (isset($filters['poli'])) {
            $query->whereIn('appointments.clinic_id', Clinic::query()->where('slug', $filters['poli'])->select('id'));
        }

        if (isset($filters['dokter'])) {
            $query->whereIn('appointments.doctor_id', Doctor::query()->where('slug', $filters['dokter'])->select('id'));
        }

        if (isset($filters['status'])) {
            $query->where('appointments.status', $filters['status']);
        }

        if (isset($filters['kanal'])) {
            $query->where('appointments.channel', $filters['kanal']);
        }

        $term = trim($filters['q'] ?? '');

        if ($term !== '') {
            $code = BookingCodeGenerator::normalize($term);

            $query->where(function (Builder $inner) use ($term, $code): void {
                if (BookingCodeGenerator::isValid($code)) {
                    $inner->orWhere('appointments.booking_code', $code);
                }

                $inner->orWhereHas('patientProfile', fn (Builder $profile): Builder => app(PatientSearch::class)->apply($profile, $term));
            });
        }

        return $query
            ->orderBy('appointments.session_date')
            ->orderBy(PracticeSession::query()->select('start_time')->whereColumn('practice_sessions.id', 'appointments.practice_session_id'))
            ->orderBy('appointments.practice_session_id')
            ->orderBy('appointments.queue_number')
            ->orderBy('appointments.id');
    }
}
