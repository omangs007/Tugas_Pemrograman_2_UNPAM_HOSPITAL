<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingChannel;
use App\Http\Requests\ListFilterRequest;
use App\Models\PatientProfile;
use App\Services\Patients\PatientSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Filter daftar pasien admin (F7A-R15, 05 §408): `q` (satu kotak: nama, no. pasien, telepon, tanggal lahir dd/mm/yyyy;
 * diterjemahkan PatientSearch::apply, F7A-R16), `kanal` (`online`|`loket`), `status` (`aktif`|`nonaktif`). Urutan tetap
 * `full_name` naik lalu `id` (tanpa `urut`). Nilai tidak valid, termasuk larik, diabaikan oleh basis (12 V-45, 00 I.5).
 */
class PatientIndexRequest extends ListFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'kanal' => ['nullable', Rule::in(array_map(fn (BookingChannel $channel): string => $channel->value, BookingChannel::cases()))],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'kanal.in' => 'Kanal tidak valid.',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return ['aktif', 'nonaktif'];
    }

    /** Kanal yang berlaku untuk tampilan filter: string kosong bila tidak ada atau tidak valid. */
    public function kanalFilter(): string
    {
        return $this->filters()['kanal'] ?? '';
    }

    /** Status yang berlaku untuk tampilan filter: string kosong bila tidak ada atau tidak valid. */
    public function statusFilter(): string
    {
        return $this->filters()['status'] ?? '';
    }

    /**
     * @param  Builder<PatientProfile>  $query
     * @return Builder<PatientProfile>
     */
    public function applyTo(Builder $query, PatientSearch $search): Builder
    {
        $filters = $this->filters();
        $model = $query->getModel();

        if (isset($filters['q'])) {
            $query = $search->apply($query, $filters['q']);
        }

        if (isset($filters['kanal'])) {
            $query->where($model->qualifyColumn('registration_channel'), $filters['kanal']);
        }

        if (isset($filters['status'])) {
            $query->where($model->qualifyColumn('is_active'), $filters['status'] === 'aktif');
        }

        return $query
            ->orderBy($model->qualifyColumn('full_name'))
            ->orderBy($model->qualifyColumn('id'));
    }
}
