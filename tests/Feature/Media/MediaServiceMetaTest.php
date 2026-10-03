<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class MediaServiceMetaTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = app(MediaService::class);
        $this->owner = User::factory()->staff()->create();
    }

    private function image(?User $owner = null, string $collection = 'gallery'): Media
    {
        return $this->service->store(ImageFixtures::jpeg(900, 600), $collection, $owner ?? $this->owner, ['alt_text' => 'Foto']);
    }

    /**
     * @return list<int>
     */
    private function orderOf(string $collection = 'gallery'): array
    {
        return Media::query()
            ->where('model_id', $this->owner->id)
            ->where('collection', $collection)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();
    }

    public function test_update_meta_trims_and_saves_alt_text_and_caption_without_touching_files(): void
    {
        $media = $this->image();
        $files = Storage::disk('public')->allFiles();

        $updated = $this->service->updateMeta($media, '  Alt baru  ', '  Keterangan baru  ');

        $this->assertSame('Alt baru', $updated->alt_text);
        $this->assertSame('Keterangan baru', $updated->caption);
        $this->assertSame('Alt baru', Media::query()->findOrFail($media->id)->alt_text);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_update_meta_stores_a_blank_caption_as_null(): void
    {
        $media = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, ['alt_text' => 'Foto', 'caption' => 'Lama']);

        $updated = $this->service->updateMeta($media, 'Foto', '   ');

        $this->assertNull($updated->caption);
        $this->assertNull(Media::query()->findOrFail($media->id)->caption);
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: string, 3: string}>
     */
    public static function invalidMeta(): array
    {
        return [
            'alt kosong' => ['', null, 'alt_text', 'Teks alternatif gambar wajib diisi.'],
            'alt hanya spasi' => ['   ', null, 'alt_text', 'Teks alternatif gambar wajib diisi.'],
            'alt null' => [null, null, 'alt_text', 'Teks alternatif gambar wajib diisi.'],
            'alt 256 karakter' => [str_repeat('a', 256), null, 'alt_text', 'Teks alternatif gambar maksimal 255 karakter.'],
            'caption 256 karakter' => ['Foto', str_repeat('k', 256), 'caption', 'Keterangan gambar maksimal 255 karakter.'],
        ];
    }

    #[DataProvider('invalidMeta')]
    public function test_update_meta_rejects_invalid_values_and_keeps_the_stored_ones(?string $alt, ?string $caption, string $key, string $message): void
    {
        $media = $this->image();

        $thrown = null;

        try {
            $this->service->updateMeta($media, $alt, $caption);
        } catch (ValidationException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertContains($message, $thrown->errors()[$key]);
        $this->assertSame('Foto', Media::query()->findOrFail($media->id)->alt_text);
    }

    public function test_reorder_sets_one_based_positions_in_the_given_order(): void
    {
        $a = $this->image();
        $b = $this->image();
        $c = $this->image();

        $this->service->reorder($this->owner, 'gallery', [$c->id, $a->id, $b->id]);

        $this->assertSame([$c->id, $a->id, $b->id], $this->orderOf());
        $this->assertSame(
            [1, 2, 3],
            Media::query()->whereIn('id', [$c->id, $a->id, $b->id])->orderBy('sort_order')->pluck('sort_order')->all(),
        );
    }

    public function test_reorder_accepts_numeric_strings_and_a_subset_of_the_collection(): void
    {
        $a = $this->image();
        $b = $this->image();
        $this->image();

        $this->service->reorder($this->owner, 'gallery', [(string) $b->id, (string) $a->id]);

        $this->assertSame(1, Media::query()->findOrFail($b->id)->sort_order);
        $this->assertSame(2, Media::query()->findOrFail($a->id)->sort_order);
    }

    public function test_reorder_rejects_ids_that_do_not_belong_to_the_owner_and_collection(): void
    {
        $a = $this->image();
        $b = $this->image();
        $foreign = $this->image(User::factory()->staff()->create());
        $otherCollection = $this->image(null, 'photos');
        $before = $this->orderOf();

        $invalid = [
            'milik pemilik lain' => [$a->id, $foreign->id],
            'koleksi lain' => [$a->id, $otherCollection->id],
            'id tidak ada' => [$a->id, 999999],
            'id ganda' => [$a->id, $a->id],
            'bukan angka' => [$a->id, 'abc'],
            'kosong' => [],
        ];

        foreach ($invalid as $label => $ids) {
            $thrown = null;

            try {
                $this->service->reorder($this->owner, 'gallery', $ids);
            } catch (ValidationException $e) {
                $thrown = $e;
            }

            $this->assertNotNull($thrown, "Tidak ditolak: {$label}");
            $this->assertSame(['ids' => ['Urutan gambar tidak valid.']], $thrown->errors(), $label);
        }

        $this->assertSame($before, $this->orderOf());
        $this->assertSame([1, 2], Media::query()->whereIn('id', [$a->id, $b->id])->orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_reorder_rejects_an_unknown_collection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->reorder($this->owner, 'avatar', [1]);
    }
}
