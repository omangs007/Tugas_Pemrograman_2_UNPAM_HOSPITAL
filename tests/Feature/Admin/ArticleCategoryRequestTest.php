<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\ArticleCategoryRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFormRequests;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-33: aturan dan pesan Indonesia untuk kategori artikel (tambah dan ubah).
 */
class ArticleCategoryRequestTest extends TestCase
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
            'name' => 'Gizi dan Nutrisi',
            'description' => 'Artikel seputar pola makan.',
            'sort_order' => '3',
        ], $overrides);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'nama kosong' => [['name' => ''], 'name', 'Nama kategori wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama kategori harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama kategori minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama kategori maksimal 100 karakter.'],
            'nama ganda' => [['name' => 'Kesehatan Umum'], 'name', 'Nama kategori sudah digunakan.'],
            'nama ganda beda huruf' => [['name' => 'KESEHATAN UMUM'], 'name', 'Nama kategori sudah digunakan.'],
            'deskripsi bukan teks' => [['description' => ['a']], 'description', 'Deskripsi harus berupa teks.'],
            'deskripsi terlalu panjang' => [['description' => str_repeat('a', 256)], 'description', 'Deskripsi maksimal 255 karakter.'],
            'deskripsi berisi tag' => [['description' => 'Teks <b>tebal</b>'], 'description', 'Deskripsi tidak boleh mengandung tag HTML.'],
            'deskripsi berisi script' => [['description' => '<script>alert(1)</script>'], 'description', 'Deskripsi tidak boleh mengandung tag HTML.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan negatif' => [['sort_order' => '-1'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_indonesian_messages_on_create(array $override, string $field, string $message): void
    {
        $this->createArticleCategory(['name' => 'Kesehatan Umum']);

        $errors = $this->validationErrors(ArticleCategoryRequest::class, $this->validPayload($override));

        $this->assertArrayHasKey($field, $errors);
        $this->assertSame($message, $errors[$field][0]);
    }

    public function test_the_same_rules_apply_on_update_but_the_own_name_is_allowed(): void
    {
        $this->createArticleCategory(['name' => 'Kesehatan Umum']);
        $category = $this->createArticleCategory(['name' => 'Gizi']);

        $own = $this->validationErrors(ArticleCategoryRequest::class, $this->validPayload(['name' => 'Gizi']), null, ['articleCategory' => $category], 'PUT');
        $this->assertSame([], $own);

        $ownOtherCase = $this->validationErrors(ArticleCategoryRequest::class, $this->validPayload(['name' => 'GIZI']), null, ['articleCategory' => $category], 'PUT');
        $this->assertSame([], $ownOtherCase);

        $taken = $this->validationErrors(ArticleCategoryRequest::class, $this->validPayload(['name' => 'Kesehatan Umum']), null, ['articleCategory' => $category], 'PUT');
        $this->assertSame('Nama kategori sudah digunakan.', $taken['name'][0]);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function validVariants(): array
    {
        return [
            'lengkap' => [[]],
            'tanpa deskripsi' => [['description' => '']],
            'deskripsi null' => [['description' => null]],
            'tanpa urutan' => [['sort_order' => '']],
            'urutan 0' => [['sort_order' => '0']],
            'urutan 9999' => [['sort_order' => '9999']],
            'batas panjang' => [['name' => str_repeat('a', 100), 'description' => str_repeat('b', 255)]],
            'teks dengan tanda lebih kecil' => [['description' => 'Tekanan <90 dan >100 perlu diperiksa']],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('validVariants')]
    public function test_valid_variants_pass(array $override): void
    {
        $this->assertSame([], $this->validationErrors(ArticleCategoryRequest::class, $this->validPayload($override)));
    }

    public function test_category_attributes_contain_only_the_editable_columns(): void
    {
        $request = $this->makeFormRequest(ArticleCategoryRequest::class, $this->validPayload([
            'name' => '  Gizi Anak  ',
            'description' => '   ',
            'sort_order' => '',
            'slug' => 'dipaksa',
            'id' => '99',
        ]));
        $request->validateResolved();

        $this->assertSame([
            'name' => 'Gizi Anak',
            'description' => null,
            'sort_order' => 0,
        ], $request->categoryAttributes());
    }

    public function test_only_staff_with_content_manage_may_use_the_request(): void
    {
        $officer = $this->createStaff('registration-officer');
        $category = $this->createArticleCategory();

        $this->assertTrue($this->makeFormRequest(ArticleCategoryRequest::class, [])->authorize());
        $this->assertTrue($this->makeFormRequest(ArticleCategoryRequest::class, [], $this->createStaff('super-admin'), ['articleCategory' => $category], 'PUT')->authorize());
        $this->assertFalse($this->makeFormRequest(ArticleCategoryRequest::class, [], $officer)->authorize());
        $this->assertFalse($this->makeFormRequest(ArticleCategoryRequest::class, [], $officer, ['articleCategory' => $category], 'PUT')->authorize());

        $this->expectException(AuthorizationException::class);

        $this->validationErrors(ArticleCategoryRequest::class, $this->validPayload(), $officer);
    }
}
