<?php

namespace Tests\Feature\Facilities;

use App\Models\RoomCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMedia;
use Tests\TestCase;

/**
 * Model RoomCategory (09 §5.1, F4B-R9 sampai F4B-R12, BR-114): label ketersediaan, batas 24 jam, tarif contoh.
 */
class RoomCategoryModelTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithMedia;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
    }

    public function test_fillable_constant_and_casts_follow_the_blueprint(): void
    {
        $this->assertSame(24, RoomCategory::STALE_AFTER_HOURS);
        $this->assertSame([
            'name', 'slug', 'short_description', 'description', 'amenities', 'bed_capacity', 'beds_available', 'sample_rate',
            'rate_unit', 'rate_note', 'is_active', 'sort_order', 'availability_updated_at', 'availability_updated_by',
            'meta_title', 'meta_description',
        ], (new RoomCategory)->getFillable());

        $casts = (new RoomCategory)->getCasts();

        $this->assertSame('array', $casts['amenities']);
        $this->assertSame('integer', $casts['bed_capacity']);
        $this->assertSame('integer', $casts['beds_available']);
        $this->assertSame('decimal:2', $casts['sample_rate']);
        $this->assertSame('boolean', $casts['is_active']);
        $this->assertSame('integer', $casts['sort_order']);
        $this->assertSame('datetime', $casts['availability_updated_at']);
    }

    public function test_unknown_attributes_are_rejected_in_strict_mode(): void
    {
        $this->expectException(MassAssignmentException::class);

        RoomCategory::factory()->make()->fill(['bukan_kolom' => 'x']);
    }

    public function test_database_defaults_and_casts(): void
    {
        $created = RoomCategory::query()->create(['name' => 'Kamar Contoh', 'short_description' => 'Ringkas.']);
        $room = RoomCategory::query()->findOrFail($created->id);

        $this->assertSame('kamar-contoh', $room->slug);
        $this->assertSame(0, $room->bed_capacity);
        $this->assertSame(0, $room->beds_available);
        $this->assertSame('per malam', $room->rate_unit);
        $this->assertTrue($room->is_active);
        $this->assertSame(0, $room->sort_order);
        $this->assertNull($room->amenities);
        $this->assertNull($room->sample_rate);
        $this->assertNull($room->availability_updated_at);
        $this->assertNull($room->availability_updated_by);
    }

    public function test_factory_defaults_and_states(): void
    {
        $plain = $this->createRoomCategory();
        $full = RoomCategory::factory()->full()->create();
        $inactive = RoomCategory::factory()->inactive()->create();
        $withoutRate = RoomCategory::factory()->withoutRate()->create();

        $this->assertSame(12, $plain->bed_capacity);
        $this->assertSame(5, $plain->beds_available);
        $this->assertTrue($plain->is_active);
        $this->assertSame('400000.00', $plain->fresh()->sample_rate);
        $this->assertSame('per malam', $plain->rate_unit);
        $this->assertSame('2026-10-05 09:00:00', $plain->fresh()->availability_updated_at->toDateTimeString(), 'Bawaan: satu jam yang lalu.');
        $this->assertNull($plain->availability_updated_by);
        $this->assertSame(0, $full->beds_available);
        $this->assertSame(12, $full->bed_capacity);
        $this->assertFalse($inactive->is_active);
        $this->assertNull($withoutRate->fresh()->sample_rate);
        $this->assertNull($withoutRate->fresh()->rate_note);
    }

    public function test_the_database_check_still_rejects_availability_above_capacity(): void
    {
        $this->expectException(QueryException::class);

        $this->createRoomCategory(['bed_capacity' => 5, 'beds_available' => 6]);
    }

    public function test_active_and_ordered_scopes(): void
    {
        $b = $this->createRoomCategory(['name' => 'Kamar B', 'sort_order' => 1]);
        $a = $this->createRoomCategory(['name' => 'Kamar A', 'sort_order' => 1]);
        $c = $this->createRoomCategory(['name' => 'Kamar C', 'sort_order' => 0]);
        $off = RoomCategory::factory()->inactive()->create(['name' => 'Kamar Z', 'sort_order' => 0]);

        $this->assertSame([$c->id, $off->id, $a->id, $b->id], RoomCategory::query()->ordered()->pluck('id')->all());
        $this->assertSame([$c->id, $a->id, $b->id], RoomCategory::query()->active()->ordered()->pluck('id')->all());
    }

    public function test_slug_is_derived_from_the_name_numbered_on_collision_and_stable_on_rename(): void
    {
        $first = $this->createRoomCategory(['name' => 'Kamar VIP/Pribadi']);
        $second = $this->createRoomCategory(['name' => 'Kamar VIP/Pribadi']);
        $explicit = $this->createRoomCategory(['name' => 'Kamar BPJS Kelas 1', 'slug' => 'bpjs-kelas-1']);

        $this->assertSame('kamar-vippribadi', $first->slug);
        $this->assertSame('kamar-vippribadi-2', $second->slug);
        $this->assertSame('bpjs-kelas-1', $explicit->slug);

        $explicit->update(['name' => 'Kamar Kelas 1 (nama baru)']);

        $this->assertSame('bpjs-kelas-1', $explicit->fresh()->slug);
        $this->assertSame(120, strlen(RoomCategory::uniqueSlug(str_repeat('a', 400))));
    }

    public function test_description_is_sanitized_and_blank_becomes_null(): void
    {
        $room = $this->createRoomCategory(['description' => '<p>Kamar <em>nyaman</em>.</p><script>alert(1)</script><img src="x" onerror="alert(2)">']);

        $stored = (string) $room->fresh()->description;

        $this->assertStringContainsString('<p>Kamar <em>nyaman</em>.</p>', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
        $this->assertSame(['description'], $room->richTextAttributes());

        $room->update(['description' => '<p><br></p>']);

        $this->assertNull($room->fresh()->description);
    }

    public function test_seo_fallbacks(): void
    {
        $this->assertSame('Judul Khusus', $this->createRoomCategory(['name' => 'Kamar A', 'meta_title' => 'Judul Khusus'])->seo_title);
        $this->assertSame('Kamar B', $this->createRoomCategory(['name' => 'Kamar B', 'meta_title' => null])->seo_title);
        $this->assertSame('Meta khusus.', $this->createRoomCategory(['short_description' => 'Ringkas.', 'meta_description' => 'Meta khusus.'])->seo_description);
        $this->assertSame('Ringkas.', $this->createRoomCategory(['short_description' => 'Ringkas.', 'meta_description' => ''])->seo_description);
    }

    public function test_occupancy_label_follows_blueprint_11_section_5_5(): void
    {
        $this->assertSame('5 dari 12 tempat tidur tersedia', $this->createRoomCategory(['bed_capacity' => 12, 'beds_available' => 5])->occupancy_label);
        $this->assertSame('0 dari 16 tempat tidur tersedia', $this->createRoomCategory(['bed_capacity' => 16, 'beds_available' => 0])->occupancy_label);
        $this->assertSame('6 dari 6 tempat tidur tersedia', $this->createRoomCategory(['bed_capacity' => 6, 'beds_available' => 6])->occupancy_label);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function updatedLabels(): array
    {
        return [
            'oktober pagi' => ['2026-10-05 08:07:00', 'Diperbarui manual oleh petugas pada 5 Okt 2026, pukul 08.07 WIB'],
            'agustus malam' => ['2026-08-17 21:05:00', 'Diperbarui manual oleh petugas pada 17 Agt 2026, pukul 21.05 WIB'],
            'desember tengah malam' => ['2026-12-01 00:00:00', 'Diperbarui manual oleh petugas pada 1 Des 2026, pukul 00.00 WIB'],
        ];
    }

    #[DataProvider('updatedLabels')]
    public function test_availability_updated_label_is_exact_and_never_says_real_time(string $at, string $expected): void
    {
        $room = $this->createRoomCategory(['availability_updated_at' => $at]);

        $this->assertSame($expected, $room->availability_updated_label);
        $this->assertStringNotContainsStringIgnoringCase('real-time', $room->availability_updated_label);
    }

    public function test_availability_updated_label_without_a_timestamp(): void
    {
        $room = $this->createRoomCategory(['availability_updated_at' => null]);

        $this->assertSame('Belum diperbarui', $room->availability_updated_label);
    }

    /** @return array<string, array{0: ?string, 1: bool}> */
    public static function staleCases(): array
    {
        return [
            'baru satu jam' => ['2026-10-05 09:00:00', false],
            'tepat 24 jam' => ['2026-10-04 10:00:00', false],
            '24 jam kurang satu menit' => ['2026-10-04 10:01:00', false],
            '24 jam lebih satu menit' => ['2026-10-04 09:59:00', true],
            'tiga hari' => ['2026-10-02 10:00:00', true],
            'belum pernah diperbarui' => [null, true],
        ];
    }

    #[DataProvider('staleCases')]
    public function test_is_availability_stale_at_the_24_hour_boundary(?string $at, bool $stale): void
    {
        $room = $this->createRoomCategory(['availability_updated_at' => $at]);

        $this->assertSame($stale, $room->is_availability_stale);
        $this->assertSame($stale, $room->fresh()->is_availability_stale);
    }

    public function test_is_full_is_true_only_when_no_bed_is_available(): void
    {
        $this->assertTrue($this->createRoomCategory(['bed_capacity' => 16, 'beds_available' => 0])->is_full);
        $this->assertFalse($this->createRoomCategory(['bed_capacity' => 16, 'beds_available' => 1])->is_full);
        $this->assertFalse($this->createRoomCategory(['bed_capacity' => 16, 'beds_available' => 16])->is_full);
        $this->assertTrue($this->createRoomCategory(['bed_capacity' => 0, 'beds_available' => 0])->is_full);
    }

    /** @return array<string, array{0: string|int|null, 1: string, 2: string}> */
    public static function rateLabels(): array
    {
        return [
            'empat ratus ribu' => [400000, 'per malam', 'Rp 400.000 per malam'],
            'satu koma dua juta' => [1200000, 'per malam', 'Rp 1.200.000 per malam'],
            'seratus lima puluh ribu' => ['150000.00', 'per malam', 'Rp 150.000 per malam'],
            'satuan lain' => [250000, 'per hari', 'Rp 250.000 per hari'],
            'tanpa tarif' => [null, 'per malam', 'Hubungi kami'],
        ];
    }

    #[DataProvider('rateLabels')]
    public function test_sample_rate_label(string|int|null $rate, string $unit, string $expected): void
    {
        $room = $this->createRoomCategory(['sample_rate' => $rate, 'rate_unit' => $unit]);

        $this->assertSame($expected, $room->sample_rate_label);
        $this->assertSame($expected, $room->fresh()->sample_rate_label);
    }

    public function test_amenities_are_cast_to_an_array(): void
    {
        $room = $this->createRoomCategory(['amenities' => ['AC', 'Televisi', 'Kamar mandi dalam']]);
        $none = $this->createRoomCategory(['amenities' => null]);

        $this->assertSame(['AC', 'Televisi', 'Kamar mandi dalam'], $room->fresh()->amenities);
        $this->assertNull($none->fresh()->amenities);
    }

    public function test_the_availability_updater_relation_and_set_null_on_user_delete(): void
    {
        $officer = User::factory()->staff()->create();
        $room = $this->createRoomCategory(['availability_updated_by' => $officer->id]);
        $this->createRoomCategory();
        $this->createRoomCategory();

        $loaded = RoomCategory::query()->with('availabilityUpdater')->orderBy('id')->get();

        $this->assertTrue($loaded[0]->availabilityUpdater->is($officer));
        $this->assertNull($loaded[1]->availabilityUpdater);

        DB::table('users')->where('id', $officer->id)->delete();

        $this->assertNull($room->fresh()->availability_updated_by);
        $this->assertNotNull($room->fresh()->availability_updated_at, 'Waktu pembaruan tetap tersimpan.');
    }

    public function test_cover_url_uses_the_room_placeholder_or_the_media_url(): void
    {
        $withCover = $this->createRoomCategory(['name' => 'Dengan Cover']);
        $this->createRoomCategory(['name' => 'Tanpa Cover']);
        $this->createRoomCategory(['name' => 'Pengisi']);

        $media = $this->createMediaRow([
            'model_type' => RoomCategory::class,
            'model_id' => $withCover->id,
            'collection' => 'cover',
            'alt_text' => 'Foto contoh Dengan Cover',
        ]);

        $loaded = RoomCategory::query()->with('media')->orderBy('id')->get();

        $this->assertSame($media->url, $loaded[0]->cover_url);
        $this->assertSame(asset('images/placeholders/room.svg'), $loaded[1]->cover_url);
    }

    public function test_listing_without_eager_loading_violates_strict_mode(): void
    {
        $this->createRoomCategory();
        $this->createRoomCategory();
        $this->createRoomCategory();

        $rooms = RoomCategory::query()->orderBy('id')->get();

        try {
            $rooms->first()->cover_url;
            $this->fail('Media harus di-eager-load pada daftar.');
        } catch (LazyLoadingViolationException) {
            $this->assertTrue(true);
        }

        $this->expectException(LazyLoadingViolationException::class);

        $rooms->first()->availabilityUpdater;
    }

    public function test_a_list_with_both_relations_eager_loaded_renders_every_accessor_without_violations(): void
    {
        $this->createRoomCategory(['name' => 'Kamar 1']);
        $this->createRoomCategory(['name' => 'Kamar 2', 'sort_order' => 1]);
        $this->createRoomCategory(['name' => 'Kamar 3', 'sort_order' => 2]);

        $rooms = RoomCategory::query()->with(['media', 'availabilityUpdater'])->ordered()->get();

        $this->assertCount(3, $rooms);

        foreach ($rooms as $room) {
            $this->assertNotSame('', $room->cover_url);
            $this->assertNotSame('', $room->occupancy_label);
            $this->assertNotSame('', $room->availability_updated_label);
            $this->assertNotSame('', $room->sample_rate_label);
            $this->assertIsBool($room->is_full);
            $this->assertIsBool($room->is_availability_stale);
            $this->assertNull($room->availabilityUpdater);
        }
    }
}
