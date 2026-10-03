<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Actor;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Stringable;
use UnitEnum;

/**
 * Penulis jejak audit (11 §6.10, 12 §8). Dipanggil di dalam transaksi operasi yang diaudit;
 * galat tulis tidak ditelan agar jejak audit tidak pernah hilang diam-diam.
 */
final class AuditLogger
{
    public const REDACTED = '[disamarkan]';

    public const CONTENT_CHANGED = '[konten diubah]';

    public const TRUNCATED_SUFFIX = '…[dipotong]';

    /** Selalu disamarkan (00 E.6). */
    private const REDACT_ALWAYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'bpjs_number',
        'payment_reference',
    ];

    /** Redaksi tingkat 2 (12 §8.1): hanya untuk aksi berawalan PII_ACTION_PREFIXES. */
    private const REDACT_PII = ['address', 'phone', 'email', 'birth_date', 'complaint', 'message'];

    private const PII_ACTION_PREFIXES = ['patient.', 'appointment.', 'contact_message.'];

    /** Teks panjang: hanya dicatat bahwa isinya berubah. */
    private const NAME_ONLY = ['body', 'bio', 'short_bio', 'description'];

    private const MAX_STRING_LENGTH = 500;

    private const MAX_DESCRIPTION_LENGTH = 255;

    private const MAX_ACTION_LENGTH = 100;

    private const MAX_USER_AGENT_LENGTH = 255;

    /**
     * Mencatat satu baris audit. `$model` wajib: untuk aksi tanpa satu baris kirim instance kosong
     * (mis. `new User`) sehingga `auditable_type` terisi dan `auditable_id` NULL (F1-R7).
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(string $action, Model $model, ?array $old, ?array $new, string $description, ?Actor $actor = null): AuditLog
    {
        if ($action === '' || mb_strlen($action) > self::MAX_ACTION_LENGTH) {
            throw new InvalidArgumentException('Nama aksi audit harus berisi 1 sampai 100 karakter.');
        }

        $actor ??= $this->resolveActor();
        $oldValues = $this->redact($old ?? [], $action, $model);
        $newValues = $this->redact($new ?? [], $action, $model);
        [$ipAddress, $userAgent] = $this->httpContext();

        return AuditLog::query()->create([
            'actor_type' => $actor->type,
            'user_id' => $actor->userId,
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'description' => mb_substr($description, 0, self::MAX_DESCRIPTION_LENGTH),
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Atribut yang berubah, tanpa `updated_at`. Setelah update tersimpan: nilai baru dari `getChanges()`
     * dan nilai lama dari `getPrevious()` (setelah save(), `getOriginal()` sudah berisi nilai baru).
     * Sebelum disimpan: `getDirty()` dibandingkan dengan `getRawOriginal()`.
     *
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public function diff(Model $model): array
    {
        $changes = $model->getChanges();
        $previous = $model->getPrevious();

        if ($changes === []) {
            $changes = $model->getDirty();
            $previous = $model->getRawOriginal();
        }

        $changes = Arr::except($changes, ['updated_at']);

        return [
            'old' => Arr::only($previous, array_keys($changes)),
            'new' => $changes,
        ];
    }

    /**
     * Redaksi tingkat 1 dan 2 (12 §8.1) serta normalisasi nilai (enum, tanggal, string panjang).
     * Rekursif; kunci dicocokkan tanpa membedakan huruf besar/kecil.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public function redact(array $values, string $action, ?Model $model = null): array
    {
        return $this->redactArray($values, $this->redactsPii($action), $this->protectedAttributes($model));
    }

    private function resolveActor(): Actor
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user->isStaff() ? Actor::staff($user) : Actor::patient($user);
        }

        return Actor::system();
    }

    /**
     * Konteks hanya untuk permintaan HTTP yang ter-route (F1-R8); CLI/tes unit ⇒ NULL.
     *
     * @return array{0: ?string, 1: ?string} IP dan user agent
     */
    private function httpContext(): array
    {
        $request = request();

        if ($request->route() === null) {
            return [null, null];
        }

        $agent = $request->userAgent();

        if ($agent !== null) {
            $clean = preg_replace('/[\x00-\x1F\x7F]/', '', mb_scrub($agent)) ?? '';
            $agent = mb_substr($clean, 0, self::MAX_USER_AGENT_LENGTH);
        }

        return [$request->ip(), $agent === '' ? null : $agent];
    }

    private function redactsPii(string $action): bool
    {
        foreach (self::PII_ACTION_PREFIXES as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Atribut `$hidden` dan atribut ber-cast `encrypted*` milik model (huruf kecil).
     *
     * @return list<string>
     */
    private function protectedAttributes(?Model $model): array
    {
        if ($model === null) {
            return [];
        }

        $attributes = $model->getHidden();

        foreach ($model->getCasts() as $attribute => $cast) {
            if (is_string($cast) && str_starts_with($cast, 'encrypted')) {
                $attributes[] = $attribute;
            }
        }

        return array_map(static fn (string $attribute): string => mb_strtolower($attribute), $attributes);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  list<string>  $protected
     * @return array<array-key, mixed>
     */
    private function redactArray(array $values, bool $redactPii, array $protected): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $name = mb_strtolower($key);

                if (in_array($name, self::REDACT_ALWAYS, true) || in_array($name, $protected, true)) {
                    $result[$key] = self::REDACTED;

                    continue;
                }

                if ($redactPii && in_array($name, self::REDACT_PII, true)) {
                    $result[$key] = self::REDACTED;

                    continue;
                }

                if (in_array($name, self::NAME_ONLY, true)) {
                    $result[$key] = self::CONTENT_CHANGED;

                    continue;
                }
            }

            $result[$key] = $this->normalize($value, $redactPii, $protected);
        }

        return $result;
    }

    /**
     * @param  list<string>  $protected
     */
    private function normalize(mixed $value, bool $redactPii, array $protected): mixed
    {
        return match (true) {
            is_array($value) => $this->redactArray($value, $redactPii, $protected),
            $value instanceof BackedEnum => $this->normalize($value->value, $redactPii, $protected),
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof Arrayable => $this->redactArray($value->toArray(), $redactPii, $protected),
            $value instanceof Stringable => $this->limitString((string) $value),
            is_string($value) => $this->limitString($value),
            default => $value,
        };
    }

    private function limitString(string $value): string
    {
        $value = mb_scrub($value);

        if (mb_strlen($value) <= self::MAX_STRING_LENGTH) {
            return $value;
        }

        return mb_substr($value, 0, self::MAX_STRING_LENGTH).self::TRUNCATED_SUFFIX;
    }
}
