<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Content\HtmlSanitizer;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Model;

/**
 * Sanitasi kolom rich text pada event `saving` (F2-R14): setiap jalur simpan model (form, seeder,
 * `update()`, `forceFill()->save()`, `updateOrCreate()`) melewatinya, sehingga yang tersimpan di DB selalu
 * hasil HtmlSanitizer::clean(). Hasil kosong (tanpa teks dan tanpa gambar) disimpan sebagai NULL. Jalur yang
 * melewati event model tidak disanitasi dan dilarang untuk kolom ini: `saveQuietly()`, `Model::withoutEvents()`,
 * `Model::insert()`, `DB::table()`, `Model::query()->update()`, dan `upsert()`.
 *
 * Setelah tersimpan (event `saved`), gambar editor yang dirujuk diikat ke model dan yang tidak lagi dirujuk
 * dilepas lewat MediaService::syncEditorImages() (08 §5.2 butir 2).
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
    abstract public function richTextAttributes(): array;

    public static function bootSanitizesRichText(): void
    {
        static::saving(function (Model $model): void {
            foreach ($model->richTextAttributes() as $attribute) {
                if (! $model->isDirty($attribute)) {
                    continue;
                }

                $value = $model->getAttributes()[$attribute] ?? null;
                $clean = app(HtmlSanitizer::class)->clean(is_string($value) ? $value : null);

                // Spasi non-breaking (U+00A0, &nbsp;, &#160;) dari Quill 2 diganti spasi biasa agar teks bisa dipatah baris.
                $clean = preg_replace('/&nbsp;|&#0*160;|&#x0*a0;|\x{00A0}/iu', ' ', $clean) ?? $clean;

                $model->setAttribute($attribute, self::isBlankRichText($clean) ? null : $clean);
            }
        });

        static::saved(function (Model $model): void {
            $changed = $model->wasRecentlyCreated;

            foreach ($model->richTextAttributes() as $attribute) {
                $changed = $changed || $model->wasChanged($attribute);
            }

            if ($changed) {
                app(MediaService::class)->syncEditorImages($model);
            }
        });
    }

    /**
     * Kosong secara visual (mis. `<p><br></p>` dari Quill atau `<p>&nbsp;</p>`): tanpa teks dan tanpa gambar.
     */
    private static function isBlankRichText(string $html): bool
    {
        if ($html === '') {
            return true;
        }

        if (stripos($html, '<img') !== false) {
            return false;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) === '';
    }
}
