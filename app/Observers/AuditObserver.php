<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Jejak audit otomatis untuk model master sederhana (11 §5.9, F2-R19): `specialty.*` dan `clinic.*`, serta konten
 * fase 4a (F4A-R8): `page.*`, `banner.*`, `article.*`, `article_category.*`, `gallery.*`, `faq.*`, `insurance_partner.*`;
 * serta fase 4b (F4B-R25): `facility.*` dan `room_category.*`. `ContactMessage` tidak diobservasi.
 * Aksi = snake_case nama kelas + "." + peristiwa. Pelaku: staf yang login, selain itu sistem.
 * `Doctor` sengaja tidak diobservasi (DoctorService mencatat `doctor.*` sendiri).
 */
final class AuditObserver
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $this->write($model, 'created', [], Arr::except($model->getAttributes(), ['created_at', 'updated_at']));
    }

    public function updated(Model $model): void
    {
        // Pada event `updated`, getChanges() sudah terisi dan getOriginal() masih berisi nilai lama.
        $changes = Arr::except($model->getChanges(), ['updated_at']);

        if ($changes === []) {
            return;
        }

        $this->write($model, 'updated', Arr::only($model->getOriginal(), array_keys($changes)), $changes);
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'deleted', Arr::except($model->getOriginal(), ['created_at', 'updated_at']), []);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function write(Model $model, string $event, array $old, array $new): void
    {
        $key = Str::snake(class_basename($model));

        $this->audit->log(
            "{$key}.{$event}",
            $model,
            $old,
            $new,
            __("messages.audit.{$event}", [
                'entity' => __("messages.entities.{$key}"),
                'name' => $this->nameOf($model),
            ]),
            $this->actor(),
        );
    }

    /**
     * Nama pada deskripsi: atribut pertama yang terisi dari `name`, `title`, `question`, dipotong 80 karakter
     * (F4A-R8). Dibaca dari atribut mentah dengan array_key_exists (F4A-R33) agar mode ketat tidak melempar untuk kolom yang tidak dimiliki model.
     */
    private function nameOf(Model $model): string
    {
        $attributes = $model->getAttributes();

        foreach (['name', 'title', 'question'] as $column) {
            if (array_key_exists($column, $attributes) && filled($attributes[$column])) {
                return Str::limit((string) $attributes[$column], 80);
            }
        }

        return '';
    }

    private function actor(): Actor
    {
        $user = Auth::user();

        return $user instanceof User && $user->isStaff() ? Actor::staff($user) : Actor::system();
    }
}
