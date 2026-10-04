<?php

namespace Tests\Feature\Models;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Review Focus 3: rich text berbahaya atau masuk lewat jalur selain form (F2-R14).
 */
class SanitizesRichTextTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: list<string>, 2: string}> payload, potongan yang tidak boleh ada, potongan yang harus ada */
    public static function dangerousPayloads(): array
    {
        return [
            'script dan atribut event' => ['<script>alert(1)</script><p onclick="x()">Halo</p>', ['<script', 'alert(1)', 'onclick'], '<p>Halo</p>'],
            'onerror pada gambar' => ['<p>Halo <img src="x" onerror="alert(1)"></p>', ['onerror', 'alert(1)', '<img'], '<p>Halo'],
            'gambar data URI' => ['<img src="data:image/png;base64,AAAA" alt="a"><p>t</p>', ['data:', '<img', 'base64'], '<p>t</p>'],
            'gambar dari host luar' => ['<img src="https://evil.test/x.png" alt="a"><p>t</p>', ['evil.test', '<img'], '<p>t</p>'],
            'tautan javascript' => ['<a href="javascript:alert(1)">klik</a>', ['javascript:', 'href'], 'klik'],
            'iframe' => ['<iframe src="https://evil.test"></iframe><p>x</p>', ['<iframe', 'evil.test'], '<p>x</p>'],
            'style dan atribut style' => ['<style>p{}</style><p style="color:red">x</p>', ['<style', 'style=', 'color:red'], '<p>x</p>'],
        ];
    }

    /**
     * @param  list<string>  $forbidden
     */
    #[DataProvider('dangerousPayloads')]
    public function test_create_stores_the_sanitizer_output(string $payload, array $forbidden, string $expected): void
    {
        $clinic = Clinic::factory()->create(['description' => $payload]);

        $stored = DB::table('clinics')->where('id', $clinic->id)->value('description');

        $this->assertSame(app(HtmlSanitizer::class)->clean($payload), $stored);
        $this->assertSame($stored, $clinic->description, 'Atribut model sama dengan yang tersimpan.');
        $this->assertStringContainsString($expected, $stored);

        foreach ($forbidden as $fragment) {
            $this->assertStringNotContainsString($fragment, $stored);
        }
    }

    public function test_internal_storage_images_and_external_links_are_kept_safely(): void
    {
        $clinic = Clinic::factory()->create([
            'description' => '<p>Isi</p><img src="/storage/editor/2026/10/a.jpg" alt="Foto"><a href="https://example.org/x">luar</a>',
        ]);

        $stored = $clinic->fresh()->description;

        $this->assertStringContainsString('<img src="/storage/editor/2026/10/a.jpg" alt="Foto">', $stored);
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $stored);
    }

    public function test_update_sanitizes_the_new_value(): void
    {
        $clinic = Clinic::factory()->create(['description' => '<p>Awal</p>']);

        $clinic->update(['description' => '<p>Baru</p><script>alert(2)</script>']);

        $this->assertSame('<p>Baru</p>', DB::table('clinics')->where('id', $clinic->id)->value('description'));
    }

    public function test_every_model_save_path_goes_through_the_sanitizer(): void
    {
        $payload = '<p>Aman</p><script>alert(3)</script>';

        $forced = Clinic::factory()->create();
        $forced->forceFill(['description' => $payload])->save();

        $assigned = Clinic::factory()->create();
        $assigned->description = $payload;
        $assigned->save();

        $viaUpsert = Clinic::query()->updateOrCreate(
            ['code' => 'UPS'],
            [
                'name' => 'Poliklinik Upsert', 'type' => 'poliklinik', 'short_description' => 'Ringkasan.',
                'location' => 'Gedung A Lt. 1', 'description' => $payload,
            ],
        );

        $viaFirstOrCreate = Clinic::query()->firstOrCreate(
            ['code' => 'FOC'],
            [
                'name' => 'Poliklinik Cari', 'type' => 'poliklinik', 'short_description' => 'Ringkasan.',
                'location' => 'Gedung A Lt. 1', 'description' => $payload,
            ],
        );

        $replica = Clinic::factory()->create()->replicate(['slug', 'code']);
        $replica->forceFill(['slug' => 'salinan', 'code' => 'SLN', 'name' => 'Salinan', 'description' => $payload])->save();

        foreach ([$forced, $assigned, $viaUpsert, $viaFirstOrCreate, $replica] as $clinic) {
            $this->assertSame('<p>Aman</p>', DB::table('clinics')->where('id', $clinic->id)->value('description'));
        }
    }

    public function test_empty_results_are_stored_as_null(): void
    {
        foreach (['', '   ', '<p></p>', "\n", '<script>alert(1)</script>'] as $empty) {
            $clinic = Clinic::factory()->create(['description' => $empty]);

            $this->assertNull(DB::table('clinics')->where('id', $clinic->id)->value('description'), json_encode($empty));
            $this->assertNull($clinic->description);
        }

        $clinic = Clinic::factory()->create(['description' => '<p>Isi</p>']);
        $clinic->update(['description' => '']);

        $this->assertNull(DB::table('clinics')->where('id', $clinic->id)->value('description'));
        $this->assertNull(Clinic::factory()->create(['description' => null])->description);
    }

    public function test_sanitizing_is_idempotent_and_ignores_unrelated_updates(): void
    {
        $clinic = Clinic::factory()->create(['description' => '<p>Isi <strong>tebal</strong></p>']);
        $first = $clinic->fresh()->description;

        $clinic->fresh()->update(['description' => $first]);
        $clinic->fresh()->update(['location' => 'Gedung B Lt. 2']);

        $this->assertSame($first, $clinic->fresh()->description);
        $this->assertSame('<p>Isi <strong>tebal</strong></p>', $first);
    }

    public function test_only_the_declared_rich_text_attribute_is_sanitized(): void
    {
        $clinic = Clinic::factory()->create([
            'short_description' => 'Ringkasan <b>tebal</b>',
            'meta_description' => 'Meta <i>miring</i>',
            'description' => '<p>Isi</p>',
        ]);

        $fresh = $clinic->fresh();

        $this->assertSame('Ringkasan <b>tebal</b>', $fresh->short_description);
        $this->assertSame('Meta <i>miring</i>', $fresh->meta_description);
    }

    public function test_plain_text_columns_of_other_models_are_not_touched(): void
    {
        $doctor = Doctor::factory()->create(['bio' => 'tekanan <90 dan >100', 'short_bio' => 'Singkat <b>x</b>']);
        $specialty = Specialty::factory()->create(['description' => 'Layanan <anak>']);

        $this->assertSame('tekanan <90 dan >100', $doctor->fresh()->bio);
        $this->assertSame('Singkat <b>x</b>', $doctor->fresh()->short_bio);
        $this->assertSame('Layanan <anak>', $specialty->fresh()->description);
    }
}
