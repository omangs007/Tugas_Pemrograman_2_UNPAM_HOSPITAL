<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactMessageStatus;
use App\Http\Requests\ListFilterRequest;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filter daftar pesan kontak (F4B-R23): `q` pada `name`, `email`, atau `subject`, dan `status` dengan nilai Indonesia
 * yang dipetakan ke enum. Tanpa `urut`: urutan tetap terbaru dahulu. Parameter yang tidak valid diabaikan oleh basis (12 V-45).
 */
class ContactMessageIndexRequest extends ListFilterRequest
{
    public const STATUS_MAP = [
        'baru' => ContactMessageStatus::New,
        'dibaca' => ContactMessageStatus::Read,
        'dibalas' => ContactMessageStatus::Replied,
        'diarsipkan' => ContactMessageStatus::Archived,
    ];

    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return array_keys(self::STATUS_MAP);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        $model = $query->getModel();

        if (isset($filters['q'])) {
            $like = LikePattern::contains($filters['q']);

            $query->where(function (Builder $inner) use ($model, $like): void {
                $inner->where($model->qualifyColumn('name'), 'like', $like)
                    ->orWhere($model->qualifyColumn('email'), 'like', $like)
                    ->orWhere($model->qualifyColumn('subject'), 'like', $like);
            });
        }

        $status = isset($filters['status']) ? (self::STATUS_MAP[$filters['status']] ?? null) : null;

        if ($status !== null) {
            $query->where($model->qualifyColumn('status'), $status->value);
        }

        return $query
            ->orderByDesc($model->qualifyColumn('created_at'))
            ->orderByDesc($model->qualifyColumn('id'));
    }
}
