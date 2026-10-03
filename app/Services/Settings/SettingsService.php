<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Media;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SettingsService
{
    public const CACHE_KEY = 'settings:all';

    /**
     * Memo per instance atas peta key => [type, value] (sumber: cache bersama).
     *
     * @var array<string, array{type: string, value: ?string}>|null
     */
    private ?array $memo = null;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Nilai bertipe menurut kolom `type`; kunci tak dikenal, NULL, atau '' ⇒ $default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->all()[$key] ?? null;

        if ($row === null || $row['value'] === null || $row['value'] === '') {
            return $default;
        }

        return $this->cast($row['type'], $row['value'], $default);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Item grup (identitas|kontak|booking|seo|privasi) untuk layar pengaturan, urut id; `value` bertipe (null bila kosong).
     *
     * @return Collection<int, array{key: string, label: string, type: string, value: mixed}>
     */
    public function group(string $group): Collection
    {
        return WebsiteSetting::query()
            ->where('group', $group)
            ->orderBy('id')
            ->get()
            ->map(fn (WebsiteSetting $setting): array => [
                'key' => $setting->key,
                'label' => $setting->label,
                'type' => $setting->type,
                'value' => $setting->value === null || $setting->value === ''
                    ? null
                    : $this->cast($setting->type, $setting->value, null),
            ])
            ->values();
    }

    public function set(string $key, mixed $value, ?User $by = null): void
    {
        $this->setMany([$key => $value], $by);
    }

    /**
     * Simpan beberapa pengaturan dalam satu transaksi. Kunci tak dikenal ⇒ InvalidArgumentException
     * (tidak ada yang tersimpan). Hanya kunci yang nilainya berubah ditulis dan dicatat dalam satu
     * baris audit `settings.updated`; cache dibuang setelah commit.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, ?User $by = null): void
    {
        if ($values === []) {
            return;
        }

        DB::transaction(function () use ($values, $by): void {
            $keys = array_map('strval', array_keys($values));
            $rows = WebsiteSetting::query()->whereIn('key', $keys)->lockForUpdate()->get()->keyBy('key');
            $unknown = array_values(array_diff($keys, $rows->keys()->all()));

            if ($unknown !== []) {
                throw new InvalidArgumentException('Kunci pengaturan tidak dikenal: '.implode(', ', $unknown).'.');
            }

            $changes = [];
            $old = [];
            $new = [];

            foreach ($values as $key => $value) {
                $setting = $rows->get((string) $key);
                $serialized = $this->serialize($setting->key, $setting->type, $value);
                $current = $setting->value === '' ? null : $setting->value;

                if ($serialized === $current) {
                    continue;
                }

                $changes[] = [$setting, $serialized];
                $old[$setting->key] = $current;
                $new[$setting->key] = $serialized;
            }

            if ($changes === []) {
                return;
            }

            foreach ($changes as [$setting, $serialized]) {
                $setting->fill(['value' => $serialized, 'updated_by' => $by?->getKey()])->save();
            }

            $this->audit->log(
                'settings.updated',
                new WebsiteSetting,
                $old,
                $new,
                'Pengaturan situs diubah: '.implode(', ', array_keys($new)).'.',
                $by !== null ? Actor::staff($by) : null,
            );

            DB::afterCommit(fn () => $this->forget());
        });
    }

    public function logoUrl(): string
    {
        $mediaId = $this->int('site.logo_media_id');

        if ($mediaId > 0) {
            $media = Media::query()->find($mediaId);

            if ($media !== null && $media->isPublic()) {
                return (string) $media->url;
            }
        }

        return asset('images/placeholders/logo.svg');
    }

    public function whatsappUrl(?string $text = null): ?string
    {
        $number = preg_replace('/\D+/', '', $this->string('contact.whatsapp')) ?? '';

        if ($number === '') {
            return null;
        }

        $url = 'https://wa.me/'.$number;
        $text = $text === null ? '' : trim($text);

        return $text === '' ? $url : $url.'?text='.rawurlencode($text);
    }

    public function forget(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Nilai ⇒ string tersimpan menurut tipe; null dan '' ⇒ NULL. Tipe tidak cocok ⇒ InvalidArgumentException.
     */
    private function serialize(string $key, string $type, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $serialized = match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'int' => is_numeric($value)
                ? (string) (int) $value
                : throw new InvalidArgumentException("Nilai pengaturan {$key} harus berupa angka bulat."),
            'json' => is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                : throw new InvalidArgumentException("Nilai pengaturan {$key} harus berupa larik."),
            default => is_scalar($value)
                ? trim((string) $value)
                : throw new InvalidArgumentException("Nilai pengaturan {$key} harus berupa teks."),
        };

        return $serialized === '' ? null : $serialized;
    }

    private function all(): array
    {
        return $this->memo ??= Cache::rememberForever(self::CACHE_KEY, static function (): array {
            $map = [];

            foreach (WebsiteSetting::query()->get(['key', 'type', 'value']) as $setting) {
                $map[$setting->key] = ['type' => $setting->type, 'value' => $setting->value];
            }

            return $map;
        });
    }

    private function cast(string $type, string $value, mixed $default): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => in_array(strtolower($value), ['1', 'true'], true),
            'json' => $this->decodeJson($value, $default),
            default => $value,
        };
    }

    private function decodeJson(string $value, mixed $default): mixed
    {
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }
}
