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
 * Jejak audit otomatis untuk model master sederhana (11 §5.9, F2-R19): `specialty.*` dan `clinic.*`.
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
                'name' => (string) $model->getAttribute('name'),
            ]),
            $this->actor(),
        );
    }

    private function actor(): Actor
    {
        $user = Auth::user();

        return $user instanceof User && $user->isStaff() ? Actor::staff($user) : Actor::system();
    }
}
