<?php

namespace Tests\Feature\Admin;

use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-35 (BR-127): pesan Indonesia per field untuk form FAQ; HTML pada pertanyaan dan jawaban ditolak (`NoHtml`).
 */
class AdminFaqValidationTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'kategori kosong' => [['category' => ''], 'category', 'Kategori FAQ wajib dipilih.'],
            'kategori tidak ada di daftar' => [['category' => 'lain-lain'], 'category', 'Kategori FAQ tidak valid.'],
            'kategori berupa larik' => [['category' => ['bpjs']], 'category', 'Kategori FAQ tidak valid.'],
            'pertanyaan kosong' => [['question' => ''], 'question', 'Pertanyaan wajib diisi.'],
            'pertanyaan bukan teks' => [['question' => ['a']], 'question', 'Pertanyaan harus berupa teks.'],
            'pertanyaan terlalu pendek' => [['question' => 'Apa?'], 'question', 'Pertanyaan minimal 5 karakter.'],
            'pertanyaan terlalu panjang' => [['question' => str_repeat('a', 256)], 'question', 'Pertanyaan maksimal 255 karakter.'],
            'pertanyaan berisi html' => [['question' => 'Apa itu <b>BPJS</b>?'], 'question', 'Pertanyaan tidak boleh mengandung tag HTML.'],
            'jawaban kosong' => [['answer' => ''], 'answer', 'Jawaban wajib diisi.'],
            'jawaban bukan teks' => [['answer' => ['a']], 'answer', 'Jawaban harus berupa teks.'],
            'jawaban terlalu pendek' => [['answer' => 'Ya'], 'answer', 'Jawaban minimal 5 karakter.'],
            'jawaban terlalu panjang' => [['answer' => str_repeat('a', 2001)], 'answer', 'Jawaban maksimal 2000 karakter.'],
            'jawaban berisi html' => [['answer' => 'Klik <a href="https://x.test">di sini</a>.'], 'answer', 'Jawaban tidak boleh mengandung tag HTML.'],
            'jawaban berisi skrip' => [['answer' => 'Halo <script>alert(1)</script>'], 'answer', 'Jawaban tidak boleh mengandung tag HTML.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'urutan negatif' => [['sort_order' => '-1'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'status terbit hilang' => [['is_published' => null], 'is_published', 'Status terbit tidak valid.'],
            'status terbit tidak valid' => [['is_published' => 'mungkin'], 'is_published', 'Status terbit tidak valid.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'category' => 'umum',
            'question' => 'Di mana lokasi rumah sakit?',
            'answer' => 'Lokasi lengkap tercantum di halaman Kontak.',
            'sort_order' => '1',
            'is_published' => '1',
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.faqs.create'))
            ->post(route('admin.faqs.store'), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.faqs.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(0, Faq::query()->count());
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_update_applies_the_same_rules_and_keeps_the_old_row(array $override, string $field, string $message): void
    {
        $faq = $this->createFaq(['category' => 'bpjs', 'question' => 'Pertanyaan asli?', 'answer' => 'Jawaban asli.']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.faqs.edit', $faq))
            ->put(route('admin.faqs.update', $faq), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.faqs.edit', $faq))
            ->assertSessionHasErrors([$field => $message]);

        $faq->refresh();
        $this->assertSame('Pertanyaan asli?', $faq->question);
        $this->assertSame('Jawaban asli.', $faq->answer);
        $this->assertSame('bpjs', $faq->category);
    }

    public function test_every_listed_category_is_accepted(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        foreach (Faq::CATEGORIES as $position => $category) {
            $this->post(route('admin.faqs.store'), array_merge($this->valid(), [
                'category' => $category,
                'question' => 'Pertanyaan kategori '.$category.'?',
                'sort_order' => (string) $position,
            ]))->assertSessionHasNoErrors();
        }

        $this->assertSame(Faq::CATEGORIES, Faq::query()->orderBy('sort_order')->pluck('category')->all());
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.faqs.create'))
            ->post(route('admin.faqs.store'), array_merge($this->valid(), ['question' => '', 'answer' => 'Jawaban lama saya']))
            ->assertRedirect(route('admin.faqs.create'));

        $this->get(route('admin.faqs.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Pertanyaan wajib diisi.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('Jawaban lama saya');
    }
}
