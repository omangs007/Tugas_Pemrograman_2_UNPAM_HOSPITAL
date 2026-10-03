<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithMedia;
use Tests\TestCase;

class MediaModelTest extends TestCase
{
    use InteractsWithMedia;
    use RefreshDatabase;

    public function test_media_table_has_the_columns_from_the_data_dictionary(): void
    {
        $this->assertSame([
            'id', 'model_type', 'model_id', 'collection', 'disk', 'directory', 'file_name',
            'original_name', 'mime_type', 'extension', 'size_bytes', 'width', 'height',
            'thumb_path', 'alt_text', 'caption', 'sort_order', 'uploaded_by', 'created_at', 'updated_at',
        ], Schema::getColumnListing('media'));
    }

    public function test_disk_defaults_to_public_and_sort_order_to_zero(): void
    {
        $attributes = $this->mediaAttributes();
        unset($attributes['disk'], $attributes['sort_order']);
        $id = DB::table('media')->insertGetId($attributes);

        $row = DB::table('media')->where('id', $id)->first();

        $this->assertSame('public', $row->disk);
        $this->assertSame(0, (int) $row->sort_order);
    }

    public function test_indexes_and_foreign_key_follow_the_data_dictionary(): void
    {
        $indexes = collect(Schema::getIndexes('media'))->keyBy('name');

        $this->assertSame(
            ['model_type', 'model_id', 'collection', 'sort_order'],
            $indexes['media_model_type_model_id_collection_sort_order_index']['columns'],
        );
        $this->assertTrue($indexes['media_file_name_unique']['unique']);

        $foreignKeys = collect(Schema::getForeignKeys('media'))->keyBy('name');

        $this->assertSame(['uploaded_by'], $foreignKeys['media_uploaded_by_foreign']['columns']);
        $this->assertSame('users', $foreignKeys['media_uploaded_by_foreign']['foreign_table']);
        $this->assertSame('set null', strtolower($foreignKeys['media_uploaded_by_foreign']['on_delete']));
    }

    public function test_file_name_must_be_unique(): void
    {
        $this->createMediaRow(['file_name' => 'sama.jpg']);

        $this->expectException(QueryException::class);

        $this->createMediaRow(['file_name' => 'sama.jpg']);
    }

    public function test_deleting_the_uploader_sets_uploaded_by_to_null(): void
    {
        $user = User::factory()->staff()->create();
        $media = $this->createMediaRow(['uploaded_by' => $user->id]);

        $user->delete();

        $this->assertNull($media->fresh()->uploaded_by);
    }

    public function test_owner_check_constraint_rejects_a_half_filled_morph_pair(): void
    {
        $this->expectException(QueryException::class);

        DB::table('media')->insert($this->mediaAttributes(['model_type' => 'App\Models\User', 'model_id' => null]));
    }

    public function test_owner_check_constraint_rejects_a_model_id_without_a_type(): void
    {
        $this->expectException(QueryException::class);

        DB::table('media')->insert($this->mediaAttributes(['model_type' => null, 'model_id' => 5]));
    }

    public function test_owner_check_constraint_accepts_both_empty_or_both_filled(): void
    {
        $orphan = $this->createMediaRow();
        $owned = $this->createMediaRow(['model_type' => 'App\Models\User', 'model_id' => 7]);

        $this->assertNull($orphan->model_id);
        $this->assertSame(7, (int) $owned->model_id);
    }

    public function test_fillable_columns_match_the_blueprint(): void
    {
        $this->assertSame([
            'model_type', 'model_id', 'collection', 'disk', 'directory', 'file_name', 'original_name',
            'mime_type', 'extension', 'size_bytes', 'width', 'height', 'thumb_path', 'alt_text',
            'caption', 'sort_order', 'uploaded_by',
        ], (new Media)->getFillable());
    }

    public function test_numeric_columns_are_cast_to_integers(): void
    {
        $media = Media::query()->find($this->createMediaRow()->id);

        $this->assertIsInt($media->size_bytes);
        $this->assertIsInt($media->width);
        $this->assertIsInt($media->height);
        $this->assertIsInt($media->sort_order);
    }

    public function test_url_and_thumb_url_use_the_public_disk(): void
    {
        Storage::fake('public');
        $media = $this->createMediaRow([
            'directory' => 'photo/2026/10',
            'file_name' => 'abc.jpg',
            'thumb_path' => 'photo/2026/10/thumbs/abc.jpg',
        ]);

        $this->assertSame(Storage::disk('public')->url('photo/2026/10/abc.jpg'), $media->url);
        $this->assertSame(Storage::disk('public')->url('photo/2026/10/thumbs/abc.jpg'), $media->thumb_url);
        $this->assertStringEndsWith('/storage/photo/2026/10/abc.jpg', $media->url);
        $this->assertTrue($media->isPublic());
    }

    public function test_thumb_url_falls_back_to_url_when_there_is_no_thumbnail(): void
    {
        Storage::fake('public');
        $media = $this->createMediaRow(['directory' => 'photo/2026/10', 'file_name' => 'abc.jpg', 'thumb_path' => null]);

        $this->assertSame($media->url, $media->thumb_url);
    }

    public function test_private_disk_has_no_public_url(): void
    {
        $media = $this->createMediaRow(['disk' => 'local']);

        $this->assertFalse($media->isPublic());
        $this->assertNull($media->url);
        $this->assertNull($media->thumb_url);
    }

    public function test_human_size_is_formatted_for_bytes_kilobytes_and_megabytes(): void
    {
        $this->assertSame('500 B', $this->createMediaRow(['size_bytes' => 500])->human_size);
        $this->assertSame('245 KB', $this->createMediaRow(['size_bytes' => 250880])->human_size);
        $this->assertSame('1,5 MB', $this->createMediaRow(['size_bytes' => 1572864])->human_size);
    }

    public function test_in_collection_scope_filters_by_collection(): void
    {
        $photo = $this->createMediaRow(['collection' => 'photo']);
        $this->createMediaRow(['collection' => 'cover']);

        $this->assertSame([$photo->id], Media::query()->inCollection('photo')->pluck('id')->all());
    }

    public function test_orphaned_scope_selects_ownerless_rows_older_than_the_given_hours(): void
    {
        $stale = $this->createMediaRow([], now()->subHours(25));
        $this->createMediaRow([], now()->subMinutes(30));
        $this->createMediaRow(['model_type' => 'App\Models\User', 'model_id' => 1], now()->subHours(72));

        $this->assertSame([$stale->id], Media::query()->orphaned()->pluck('id')->all());
        $this->assertSame([$stale->id], Media::query()->orphaned(1)->pluck('id')->all());
        $this->assertCount(2, Media::query()->orphaned(0)->get());
    }

    public function test_relations_resolve_the_uploader_and_the_owner(): void
    {
        $user = User::factory()->staff()->create();
        $media = $this->createMediaRow([
            'uploaded_by' => $user->id,
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->id,
        ]);

        $media = Media::query()->with(['uploader', 'model'])->find($media->id);

        $this->assertTrue($media->uploader->is($user));
        $this->assertTrue($media->model->is($user));
    }
}
