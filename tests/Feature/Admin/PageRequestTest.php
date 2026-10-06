<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFormRequests;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-30 + BR-121: aturan dan pesan Indonesia untuk halaman sistem; `body` dilarang untuk kunci tanpa isi.
 */
class PageRequestTest extends TestCase
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
            'title' => 'Profil Rumah Sakit',
            'subtitle' => 'Mengenal UNPAM HOSPITAL',
            'body' => '<p>Isi halaman.</p>',
            'meta_title' => 'Profil',
            'meta_description' => 'Profil singkat.',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    private function errorsFor(string $key, array $data): array
    {
        return $this->validationErrors(PageRequest::class, $data, null, ['page' => $this->createPage($key)], 'PUT');
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'judul kosong' => ['about', ['title' => ''], 'title', 'Judul wajib diisi.'],
            'judul bukan teks' => ['about', ['title' => ['a']], 'title', 'Judul harus berupa teks.'],
            'judul terlalu pendek' => ['about', ['title' => 'Ab'], 'title', 'Judul minimal 3 karakter.'],
            'judul terlalu panjang' => ['about', ['title' => str_repeat('a', 151)], 'title', 'Judul maksimal 150 karakter.'],
            'subjudul bukan teks' => ['about', ['subtitle' => ['a']], 'subtitle', 'Subjudul harus berupa teks.'],
            'subjudul terlalu panjang' => ['about', ['subtitle' => str_repeat('a', 256)], 'subtitle', 'Subjudul maksimal 255 karakter.'],
            'isi bukan teks' => ['about', ['body' => ['a']], 'body', 'Isi halaman harus berupa teks.'],
            'isi terlalu panjang' => ['about', ['body' => str_repeat('a', 100001)], 'body', 'Isi halaman maksimal 100.000 karakter.'],
            'isi untuk beranda' => ['home', ['body' => '<p>Tidak boleh</p>'], 'body', 'Halaman ini tidak memiliki isi yang dapat diedit.'],
            'isi untuk faq' => ['faq', ['body' => '<p>Tidak boleh</p>'], 'body', 'Halaman ini tidak memiliki isi yang dapat diedit.'],
            'isi untuk kontak' => ['contact', ['body' => '<p>Tidak boleh</p>'], 'body', 'Halaman ini tidak memiliki isi yang dapat diedit.'],
            'meta title bukan teks' => ['about', ['meta_title' => ['a']], 'meta_title', 'Meta title harus berupa teks.'],
            'meta title terlalu panjang' => ['about', ['meta_title' => str_repeat('a', 71)], 'meta_title', 'Meta title maksimal 70 karakter.'],
            'meta description bukan teks' => ['about', ['meta_description' => ['a']], 'meta_description', 'Meta description harus berupa teks.'],
            'meta description terlalu panjang' => ['about', ['meta_description' => str_repeat('a', 161)], 'meta_description', 'Meta description maksimal 160 karakter.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_indonesian_messages(string $key, array $override, string $field, string $message): void
    {
        $errors = $this->errorsFor($key, $this->validPayload($override));

        $this->assertArrayHasKey($field, $errors);
        $this->assertSame($message, $errors[$field][0]);
    }

    public function test_every_page_key_without_body_prohibits_it_and_every_key_with_body_allows_it(): void
    {
        foreach (Page::KEYS as $key) {
            $errors = $this->errorsFor($key, $this->validPayload());

            if (in_array($key, Page::KEYS_WITH_BODY, true)) {
                $this->assertArrayNotHasKey('body', $errors, "Kunci {$key} seharusnya boleh memiliki isi.");
            } else {
                $this->assertArrayHasKey('body', $errors, "Kunci {$key} seharusnya melarang isi.");
            }

            Page::query()->where('key', $key)->delete();
        }
    }

    public function test_optional_fields_may_be_empty_and_an_empty_body_is_allowed_for_pages_without_body(): void
    {
        $this->assertSame([], $this->errorsFor('home', [
            'title' => 'Beranda',
            'subtitle' => null,
            'body' => null,
            'meta_title' => null,
            'meta_description' => null,
        ]));

        $this->assertSame([], $this->errorsFor('clinics-index', [
            'title' => 'Layanan dan Poliklinik',
            'body' => '',
        ]));
    }

    public function test_boundary_lengths_are_accepted(): void
    {
        $errors = $this->errorsFor('about', $this->validPayload([
            'title' => str_repeat('a', 150),
            'subtitle' => str_repeat('b', 255),
            'body' => str_repeat('c', 100000),
            'meta_title' => str_repeat('d', 70),
            'meta_description' => str_repeat('e', 160),
        ]));

        $this->assertSame([], $errors);
    }

    public function test_page_attributes_contains_only_editable_columns_and_turns_blank_strings_into_null(): void
    {
        $page = $this->createPage('about');
        $request = $this->makeFormRequest(PageRequest::class, $this->validPayload([
            'subtitle' => '   ',
            'meta_title' => '',
            'key' => 'hacked',
            'is_system' => '0',
            'updated_by' => '99',
        ]), null, ['page' => $page], 'PUT');
        $request->validateResolved();

        $this->assertSame([
            'title' => 'Profil Rumah Sakit',
            'subtitle' => null,
            'meta_title' => null,
            'meta_description' => 'Profil singkat.',
            'body' => '<p>Isi halaman.</p>',
        ], $request->pageAttributes());
    }

    public function test_page_attributes_omits_body_for_a_page_without_body(): void
    {
        $page = $this->createPage('home');
        $request = $this->makeFormRequest(PageRequest::class, $this->validPayload(['body' => null]), null, ['page' => $page], 'PUT');
        $request->validateResolved();

        $this->assertArrayNotHasKey('body', $request->pageAttributes());
    }

    public function test_only_staff_with_content_manage_may_use_the_request(): void
    {
        $page = $this->createPage('about');
        $officer = $this->createStaff('registration-officer');

        $this->assertTrue($this->makeFormRequest(PageRequest::class, [], null, ['page' => $page], 'PUT')->authorize());
        $this->assertTrue($this->makeFormRequest(PageRequest::class, [], $this->createStaff('super-admin'), ['page' => $page], 'PUT')->authorize());
        $this->assertFalse($this->makeFormRequest(PageRequest::class, [], $officer, ['page' => $page], 'PUT')->authorize());
        $this->assertFalse($this->makeFormRequest(PageRequest::class, [], null, [], 'PUT')->authorize(), 'Tanpa model halaman tidak ada yang boleh disimpan.');

        $this->expectException(AuthorizationException::class);

        $this->validationErrors(PageRequest::class, $this->validPayload(), $officer, ['page' => $page], 'PUT');
    }
}
