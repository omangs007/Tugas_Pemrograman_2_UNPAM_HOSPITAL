<?php

namespace App\Http\Requests\Admin;

use App\Enums\ExceptionType;
use App\Http\Requests\ListFilterRequest;
use App\Models\ScheduleException;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Filter daftar pengecualian (F3-R22, 07 W-17): `q` (alasan), `jenis` (ExceptionType), `status`
 * mendatang (bawaan; `end_date` >= hari ini) / lampau / semua. Nilai tidak valid diabaikan oleh basis.
 */
class ScheduleExceptionIndexRequest extends ListFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'jenis' => ['nullable', 'string', Rule::in(ExceptionType::values())],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'jenis.string' => 'Jenis tidak valid.',
            'jenis.in' => 'Jenis tidak valid.',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return ['mendatang', 'lampau', 'semua'];
    }

    /** Status yang berlaku: `mendatang` bila tidak ada atau tidak valid. */
    public function statusFilter(): string
    {
        return $this->filters()['status'] ?? 'mendatang';
    }

    /**
     * @param  Builder<ScheduleException>  $query
     * @return Builder<ScheduleException>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        $table = $query->getModel()->getTable();
        $status = $this->statusFilter();
        $today = today()->toDateString();

        if ($status === 'mendatang') {
            $query->where($table.'.end_date', '>=', $today);
        } elseif ($status === 'lampau') {
            $query->where($table.'.end_date', '<', $today);
        }

        if (isset($filters['q'])) {
            $like = LikePattern::contains($filters['q']);
            $query->where($table.'.reason', 'like', $like);
        }

        if (isset($filters['jenis'])) {
            $query->where($table.'.type', $filters['jenis']);
        }

        return $status === 'lampau'
            ? $query->orderByDesc($table.'.start_date')->orderBy($table.'.id')
            : $query->orderBy($table.'.start_date')->orderBy($table.'.id');
    }
}
