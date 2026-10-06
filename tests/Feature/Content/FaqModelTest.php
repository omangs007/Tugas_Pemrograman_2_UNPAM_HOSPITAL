<?php

namespace Tests\Feature\Content;

use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\TestCase;

class FaqModelTest extends TestCase
{
    use InteractsWithContent;
    use RefreshDatabase;

    public function test_categories_and_labels_follow_the_blueprint(): void
    {
        $this->assertSame(['pendaftaran', 'bpjs', 'layanan', 'umum'], Faq::CATEGORIES);
        $this->assertSame(
            ['pendaftaran' => 'Pendaftaran', 'bpjs' => 'BPJS', 'layanan' => 'Layanan', 'umum' => 'Umum'],
            Faq::CATEGORY_LABELS,
        );
        $this->assertSame(
            ['category', 'question', 'answer', 'sort_order', 'is_published'],
            (new Faq)->getFillable(),
        );
    }

    #[DataProvider('categories')]
    public function test_category_label(string $category, string $label): void
    {
        $this->assertSame($label, $this->createFaq(['category' => $category])->category_label);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function categories(): array
    {
        return [
            'pendaftaran' => ['pendaftaran', 'Pendaftaran'],
            'bpjs' => ['bpjs', 'BPJS'],
            'layanan' => ['layanan', 'Layanan'],
            'umum' => ['umum', 'Umum'],
            'di luar daftar memakai nilai mentah' => ['lain-lain', 'lain-lain'],
        ];
    }

    public function test_defaults_and_factory_states(): void
    {
        $created = Faq::query()->create(['category' => 'umum', 'question' => 'Apa kabar?', 'answer' => 'Baik.']);
        $fresh = Faq::query()->findOrFail($created->id);

        $this->assertFalse($fresh->is_published);
        $this->assertSame(0, $fresh->sort_order);

        $this->assertTrue($this->createFaq()->is_published);
        $this->assertFalse(Faq::factory()->unpublished()->create()->is_published);
        $this->assertSame('bpjs', Faq::factory()->category('bpjs')->create()->category);
        $this->assertSame('umum', $this->createFaq()->category);
    }

    public function test_published_ordered_and_in_category_scopes(): void
    {
        $b = $this->createFaq(['category' => 'bpjs', 'question' => 'B?', 'sort_order' => 2]);
        $a1 = $this->createFaq(['category' => 'bpjs', 'question' => 'A1?', 'sort_order' => 1]);
        $a2 = $this->createFaq(['category' => 'bpjs', 'question' => 'A2?', 'sort_order' => 1]);
        $umum = $this->createFaq(['category' => 'umum', 'question' => 'U?', 'sort_order' => 0]);
        $draft = $this->createFaq(['category' => 'bpjs', 'question' => 'Draf?', 'is_published' => false, 'sort_order' => 0]);

        $this->assertSame([$a1->id, $a2->id, $b->id], Faq::query()->published()->inCategory('bpjs')->ordered()->pluck('id')->all());
        $this->assertSame([$draft->id], Faq::query()->inCategory('bpjs')->where('is_published', false)->pluck('id')->all());
        $this->assertSame([$umum->id], Faq::query()->published()->inCategory('umum')->pluck('id')->all());
        $this->assertSame([$umum->id, $draft->id, $a1->id, $a2->id, $b->id], Faq::query()->ordered()->pluck('id')->all());
        $this->assertSame(0, Faq::query()->inCategory('layanan')->count());
    }

    public function test_answer_is_stored_as_plain_text_without_sanitization(): void
    {
        $faq = $this->createFaq(['answer' => "Baris satu.\nBaris dua <b>tebal</b>."]);

        $this->assertSame("Baris satu.\nBaris dua <b>tebal</b>.", $faq->fresh()->answer);
    }
}
