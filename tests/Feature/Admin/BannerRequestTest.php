<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\BannerIndexRequest;
use App\Http\Requests\Admin\BannerRequest;
use App\Models\Banner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFormRequests;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-31: aturan dan pesan Indonesia untuk banner (konteks ubah, supaya `image` opsional), pemetaan atribut,
 * dan filter daftar `BannerIndexRequest` (F4A-R14).
 */
class BannerRequestTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithFormRequests;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Promo Pemeriksaan Gratis',
            'subtitle' => 'Berlaku sampai akhir bulan',
            'button_label' => 'Lihat Dokter',
            'button_url' => '/dokter',
            'sort_order' => '2',
            'is_active' => '1',
            'starts_at' => '2026-10-10T08:00',
            'ends_at' => '2026-10-20T17:30',
            'image_alt' => 'Banner promo',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    private function updateErrors(array $data): array
    {
        return $this->validationErrors(BannerRequest::class, $data, null, ['banner' => $this->createBanner()], 'PUT');
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        $linkMessage = 'Tautan harus berupa alamat internal (diawali /) atau URL https.';

        return [
            'judul kosong' => [['title' => ''], 'title', 'Judul banner wajib diisi.'],
            'judul bukan teks' => [['title' => ['a']], 'title', 'Judul banner harus berupa teks.'],
            'judul terlalu pendek' => [['title' => 'Ab'], 'title', 'Judul banner minimal 3 karakter.'],
            'judul terlalu panjang' => [['title' => str_repeat('a', 101)], 'title', 'Judul banner maksimal 100 karakter.'],
            'subjudul bukan teks' => [['subtitle' => ['a']], 'subtitle', 'Subjudul banner harus berupa teks.'],
            'subjudul terlalu panjang' => [['subtitle' => str_repeat('a', 201)], 'subtitle', 'Subjudul banner maksimal 200 karakter.'],
            'label terlalu panjang' => [['button_label' => str_repeat('a', 31)], 'button_label', 'Label tombol maksimal 30 karakter.'],
            'label tanpa tautan' => [['button_label' => 'Lihat', 'button_url' => ''], 'button_url', 'Tautan tombol wajib diisi bila label diisi.'],
            'tautan tanpa label' => [['button_label' => '', 'button_url' => '/dokter'], 'button_label', 'Label tombol wajib diisi bila tautan diisi.'],
            'tautan terlalu panjang' => [['button_url' => '/'.str_repeat('a', 255)], 'button_url', 'Tautan tombol maksimal 255 karakter.'],
            'tautan javascript' => [['button_url' => 'javascript:alert(1)'], 'button_url', $linkMessage],
            'tautan data' => [['button_url' => 'data:text/html,x'], 'button_url', $linkMessage],
            'tautan http' => [['button_url' => 'http://contoh.test'], 'button_url', $linkMessage],
            'tautan protokol relatif' => [['button_url' => '//evil.test'], 'button_url', $linkMessage],
            'tautan relatif tanpa garis miring' => [['button_url' => 'dokter'], 'button_url', $linkMessage],
            'tautan https tanpa host' => [['button_url' => 'https://'], 'button_url', $linkMessage],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan negatif' => [['sort_order' => '-1'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'status aktif hilang' => [['is_active' => null], 'is_active', 'Status aktif tidak valid.'],
            'status aktif tidak valid' => [['is_active' => 'mungkin'], 'is_active', 'Status aktif tidak valid.'],
            'mulai bukan tanggal' => [['starts_at' => 'kapan-saja'], 'starts_at', 'Format waktu mulai tayang tidak valid.'],
            'selesai bukan tanggal' => [['ends_at' => 'nanti'], 'ends_at', 'Format waktu selesai tayang tidak valid.'],
            'selesai sebelum mulai' => [['starts_at' => '2026-10-20T08:00', 'ends_at' => '2026-10-10T08:00'], 'ends_at', 'Waktu selesai tayang harus setelah waktu mulai tayang.'],
            'selesai sama dengan mulai' => [['starts_at' => '2026-10-20T08:00', 'ends_at' => '2026-10-20T08:00'], 'ends_at', 'Waktu selesai tayang harus setelah waktu mulai tayang.'],
            'alt bukan teks' => [['image_alt' => ['a']], 'image_alt', 'Teks alternatif banner harus berupa teks.'],
            'alt terlalu panjang' => [['image_alt' => str_repeat('a', 256)], 'image_alt', 'Teks alternatif banner maksimal 255 karakter.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_indonesian_messages(array $override, string $field, string $message): void
    {
        $errors = $this->updateErrors($this->validPayload($override));

        $this->assertArrayHasKey($field, $errors);
        $this->assertSame($message, $errors[$field][0]);
    }

    public function test_a_valid_payload_passes_in_the_update_context_without_an_image(): void
    {
        $this->assertSame([], $this->updateErrors($this->validPayload()));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function validVariants(): array
    {
        return [
            'tanpa periode dan tombol' => [['starts_at' => '', 'ends_at' => '', 'button_label' => '', 'button_url' => '']],
            'hanya mulai' => [['ends_at' => '']],
            'hanya selesai' => [['starts_at' => '']],
            'tautan https' => [['button_url' => 'https://contoh.test/promo?x=1']],
            'tautan internal dengan query' => [['button_url' => '/jadwal-dokter?poli=anak']],
            'batas panjang' => [['title' => str_repeat('a', 100), 'subtitle' => str_repeat('b', 200), 'button_label' => str_repeat('c', 30)]],
            'urutan 0 dan 9999' => [['sort_order' => '9999']],
            'urutan kosong' => [['sort_order' => '']],
            'dimatikan' => [['is_active' => '0']],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('validVariants')]
    public function test_valid_variants_pass(array $override): void
    {
        $this->assertSame([], $this->updateErrors($this->validPayload($override)));
    }

    public function test_the_image_is_required_when_creating_but_optional_when_editing(): void
    {
        $createErrors = $this->validationErrors(BannerRequest::class, $this->validPayload());

        $this->assertArrayHasKey('image', $createErrors);
        $this->assertSame('Gambar banner wajib dipilih.', $createErrors['image'][0]);
        $this->assertArrayNotHasKey('image', $this->updateErrors($this->validPayload()));
    }

    public function test_banner_attributes_map_the_validated_input(): void
    {
        $request = $this->makeFormRequest(BannerRequest::class, $this->validPayload([
            'subtitle' => '   ',
            'sort_order' => '',
            'id' => '99',
            'image_alt' => 'Alt',
        ]), null, ['banner' => $this->createBanner()], 'PUT');
        $request->validateResolved();

        $this->assertSame([
            'title' => 'Promo Pemeriksaan Gratis',
            'subtitle' => null,
            'button_label' => 'Lihat Dokter',
            'button_url' => '/dokter',
            'sort_order' => 0,
            'is_active' => true,
            'starts_at' => '2026-10-10T08:00',
            'ends_at' => '2026-10-20T17:30',
        ], $request->bannerAttributes());
        $this->assertNull($request->imageFile());
        $this->assertSame('Alt', $request->imageAlt());
    }

    public function test_banner_attributes_turn_blank_period_and_button_into_null(): void
    {
        $request = $this->makeFormRequest(BannerRequest::class, $this->validPayload([
            'button_label' => '',
            'button_url' => '',
            'starts_at' => '',
            'ends_at' => '',
            'is_active' => '0',
        ]), null, ['banner' => $this->createBanner()], 'PUT');
        $request->validateResolved();

        $attributes = $request->bannerAttributes();

        $this->assertNull($attributes['button_label']);
        $this->assertNull($attributes['button_url']);
        $this->assertNull($attributes['starts_at']);
        $this->assertNull($attributes['ends_at']);
        $this->assertFalse($attributes['is_active']);
    }

    public function test_only_staff_with_content_manage_may_use_the_request(): void
    {
        $officer = $this->createStaff('registration-officer');
        $banner = $this->createBanner();

        $this->assertTrue($this->makeFormRequest(BannerRequest::class, [])->authorize());
        $this->assertTrue($this->makeFormRequest(BannerRequest::class, [], $this->createStaff('super-admin'), ['banner' => $banner], 'PUT')->authorize());
        $this->assertFalse($this->makeFormRequest(BannerRequest::class, [], $officer)->authorize());
        $this->assertFalse($this->makeFormRequest(BannerRequest::class, [], $officer, ['banner' => $banner], 'PUT')->authorize());

        $this->expectException(AuthorizationException::class);

        $this->validationErrors(BannerRequest::class, $this->validPayload(), $officer);
    }

    // --- BannerIndexRequest --------------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $query
     */
    private function indexRequest(array $query): BannerIndexRequest
    {
        $request = BannerIndexRequest::create('/_uji/filter', 'GET', $query);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        return $request;
    }

    public function test_index_filters_accept_valid_values(): void
    {
        $request = $this->indexRequest(['q' => 'promo', 'status' => 'aktif', 'urut' => '-judul']);

        $this->assertEquals(['q' => 'promo', 'status' => 'aktif', 'urut' => '-judul'], $request->filters());
    }

    public function test_index_filters_ignore_invalid_values_without_an_error(): void
    {
        $request = $this->indexRequest(['q' => str_repeat('a', 101), 'status' => 'zzz', 'urut' => 'kuota', 'kembali' => 'https://situs-lain.test']);

        $this->assertSame([], $request->filters());
        $this->assertSame('Filter tidak valid dan diabaikan.', session('warning'));
    }

    public function test_index_apply_to_orders_by_sort_order_then_id_and_escapes_like_characters(): void
    {
        $this->createBanner(['title' => 'C Terakhir', 'sort_order' => 5]);
        $first = $this->createBanner(['title' => 'A Sama', 'sort_order' => 1]);
        $second = $this->createBanner(['title' => 'B Sama', 'sort_order' => 1]);
        $this->createBanner(['title' => '50% Diskon_Sehat', 'sort_order' => 9, 'is_active' => false]);

        $ids = fn (array $query): array => $this->indexRequest($query)->applyTo(Banner::query())->pluck('id')->all();
        $titles = fn (array $query): array => $this->indexRequest($query)->applyTo(Banner::query())->pluck('title')->all();

        $this->assertSame([$first->id, $second->id], array_slice($ids([]), 0, 2));
        $this->assertSame(['A Sama', 'B Sama', 'C Terakhir', '50% Diskon_Sehat'], $titles([]));
        $this->assertCount(1, $ids(['q' => '50%']));
        $this->assertCount(1, $ids(['q' => '_Sehat']));
        $this->assertCount(0, $ids(['q' => '%%']));
        $this->assertCount(3, $ids(['status' => 'aktif']));
        $this->assertCount(1, $ids(['status' => 'nonaktif']));
        $this->assertSame(['50% Diskon_Sehat', 'A Sama', 'B Sama', 'C Terakhir'], $titles(['urut' => 'judul']));
        $this->assertSame(['C Terakhir', 'B Sama', 'A Sama', '50% Diskon_Sehat'], $titles(['urut' => '-judul']));
    }
}
