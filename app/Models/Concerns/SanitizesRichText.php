<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Content\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Sanitasi kolom rich text pada event `saving` (F2-R14): setiap jalur simpan model (form, seeder,
 * `update()`, `forceFill()->save()`, `updateOrCreate()`) melewatinya, sehingga yang tersimpan di DB selalu
 * hasil HtmlSanitizer::clean(). Hasil kosong disimpan sebagai NULL. Operasi massal di tingkat query
 * (`Model::query()->update()`, `upsert()`) tidak memicu event model dan dilarang untuk kolom ini.
 *
 * @mixin Model
 */
trait SanitizesRichText
{
    /**
     * Nama atribut rich text milik model, mis. `['description']`.
     *
     * @return list<string>
     */
    abstract protected function richTextAttributes(): array;

    public static function bootSanitizesRichText(): void
    {
        static::saving(function (Model $model): void {
            foreach ($model->richTextAttributes() as $attribute) {
                if (! $model->isDirty($attribute)) {
                    continue;
                }

                $value = $model->getAttributes()[$attribute] ?? null;
                $clean = app(HtmlSanitizer::class)->clean(is_string($value) ? $value : null);

                $model->setAttribute($attribute, $clean === '' ? null : $clean);
            }
        });
    }
}
