<?php

namespace Tests\Feature\Public;

use App\Models\Faq;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #21 `faq` (05 hal. 21, F4A-R22): FAQ terbit dikelompokkan per kategori dalam urutan `Faq::CATEGORIES` dengan
 * accordion Bootstrap; jawaban dan pertanyaan di-escape; judul dan SEO dari baris `pages` `faq` (CRD-10).
 */
class PublicFaqTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    private function fakeRoute(string $name, string $uri): void
    {
        if (! Route::has($name)) {
            Route::middleware('web')->get($uri, fn () => 'x')->name($name);
            Route::getRoutes()->refreshNameLookups();
        }
    }

    // --- header, SEO, breadcrumb (CRD-10) --------------------------------------------------------------------

    public function test_it_renders_the_default_header_seo_and_breadcrumb(): void
    {
        $this->createFaq();

        $response = $this->get(route('faq'))->assertOk();

        $response->assertSee('<title>Pertanyaan yang Sering Diajukan — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Pertanyaan yang Sering Diajukan</h1>', false)
            ->assertSee('Jawaban atas pertanyaan umum')
            ->assertSee('<meta name="description" content="Pertanyaan umum tentang pendaftaran, BPJS, layanan, dan data pribadi.">', false)
            ->assertSee('<meta property="og:title" content="Pertanyaan yang Sering Diajukan">', false)
            ->assertSee('<link rel="canonical" href="'.route('faq').'">', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'FAQ'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">FAQ</li>', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_the_title_and_seo_follow_the_faq_page_row(): void
    {
        $this->createPage('faq', [
            'title' => 'Tanya Jawab',
            'subtitle' => 'Jawaban singkat untuk Anda',
            'meta_title' => 'Tanya Jawab Meta',
            'meta_description' => 'Deskripsi tanya jawab.',
        ]);
        $this->createFaq();

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('<title>Tanya Jawab Meta — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Tanya Jawab</h1>', false)
            ->assertSee('Jawaban singkat untuk Anda')
            ->assertSee('<meta name="description" content="Deskripsi tanya jawab.">', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">FAQ</li>', false);
    }

    // --- pengelompokan per kategori (F4A-R22) ----------------------------------------------------------------------

    public function test_faqs_are_grouped_by_category_in_the_fixed_order_with_labels(): void
    {
        $this->createFaq(['category' => 'umum', 'question' => 'Pertanyaan umum satu?', 'sort_order' => 1]);
        $this->createFaq(['category' => 'bpjs', 'question' => 'Pertanyaan BPJS dua?', 'sort_order' => 2]);
        $this->createFaq(['category' => 'pendaftaran', 'question' => 'Pertanyaan daftar kedua?', 'sort_order' => 2]);
        $this->createFaq(['category' => 'pendaftaran', 'question' => 'Pertanyaan daftar pertama?', 'sort_order' => 1]);
        $this->createFaq(['category' => 'bpjs', 'question' => 'Pertanyaan BPJS satu?', 'sort_order' => 1]);

        $response = $this->get(route('faq'))->assertOk();

        $response->assertSeeInOrder([
            '<h2 class="h4 mb-3" id="faq-pendaftaran">Pendaftaran</h2>',
            'Pertanyaan daftar pertama?',
            'Pertanyaan daftar kedua?',
            '<h2 class="h4 mb-3" id="faq-bpjs">BPJS</h2>',
            'Pertanyaan BPJS satu?',
            'Pertanyaan BPJS dua?',
            '<h2 class="h4 mb-3" id="faq-umum">Umum</h2>',
            'Pertanyaan umum satu?',
        ], false);
        $this->assertSame(3, substr_count($response->getContent(), '<h2 class="h4 mb-3" id="faq-'));
    }

    public function test_a_category_without_published_faqs_is_skipped(): void
    {
        $this->createFaq(['category' => 'layanan', 'question' => 'Hanya draf layanan?', 'is_published' => false]);
        $this->createFaq(['category' => 'umum', 'question' => 'Pertanyaan terbit?']);

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('id="faq-umum"', false)
            ->assertDontSee('id="faq-layanan"', false)
            ->assertDontSee('Layanan</h2>', false)
            ->assertDontSee('Hanya draf layanan?');
    }

    public function test_unpublished_faqs_are_not_shown(): void
    {
        $this->createFaq(['question' => 'Pertanyaan terbit?']);
        $this->createFaq(['question' => 'Pertanyaan draf?', 'answer' => 'Jawaban draf rahasia.', 'is_published' => false]);

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Pertanyaan terbit?')
            ->assertDontSee('Pertanyaan draf?')
            ->assertDontSee('Jawaban draf rahasia.');
    }

    // --- accordion ----------------------------------------------------------------------------------------------------

    public function test_every_question_is_a_collapsed_accordion_button_linked_to_its_answer(): void
    {
        $first = $this->createFaq(['category' => 'bpjs', 'question' => 'Apakah BPJS diterima?', 'answer' => 'Ya, sesuai ketentuan.']);
        $second = $this->createFaq(['category' => 'bpjs', 'question' => 'Apa syaratnya?', 'answer' => 'Bawa kartu peserta.']);

        $response = $this->get(route('faq'))->assertOk();
        $html = $response->getContent();

        $response->assertSee('<h3 class="accordion-header">', false)
            ->assertSee('data-bs-toggle="collapse"', false)
            ->assertSee('data-bs-target="#faq-jawaban-'.$first->id.'"', false)
            ->assertSee('aria-controls="faq-jawaban-'.$first->id.'"', false)
            ->assertSee('id="faq-jawaban-'.$second->id.'"', false)
            ->assertSee('data-bs-parent="#faq-accordion-bpjs"', false)
            ->assertSee('Ya, sesuai ketentuan.')
            ->assertSee('Bawa kartu peserta.');
        $this->assertSame(2, preg_match_all('/class="accordion-button collapsed"[^>]*aria-expanded="false"/', $html));
        $this->assertSame(0, preg_match_all('/class="accordion-button[^"]*"[^>]*aria-expanded="true"/', $html));
        $this->assertSame(2, substr_count($html, 'data-bs-toggle="collapse"'));
        $this->assertSame(2, substr_count($html, 'class="accordion-button collapsed"'));
        $this->assertSame(2, substr_count($html, 'class="accordion-collapse collapse"'));
        $this->assertSame(0, substr_count($html, 'collapse show'));
    }

    public function test_the_page_keeps_the_heading_hierarchy_h1_h2_h3(): void
    {
        $this->createFaq(['category' => 'bpjs']);
        $this->createFaq(['category' => 'umum']);
        $this->createFaq(['category' => 'umum']);

        $html = (string) $this->get(route('faq'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(2, substr_count($html, '<h2 class="h4 mb-3"'));
        $this->assertSame(3, substr_count($html, '<h3 class="accordion-header">'));
    }

    // --- escape: pertanyaan dan jawaban teks biasa -----------------------------------------------------------------------

    public function test_question_and_answer_are_escaped_and_keep_line_breaks_through_css(): void
    {
        $this->createFaq([
            'question' => 'Apakah <b>tebal</b> aman?',
            'answer' => "Baris satu <script>alert(1)</script>\n\nBaris dua <i>miring</i>",
        ]);

        $response = $this->get(route('faq'))->assertOk();

        $response->assertSee('Apakah &lt;b&gt;tebal&lt;/b&gt; aman?', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;i&gt;miring&lt;/i&gt;', false)
            ->assertSee('class="accordion-body faq-answer"', false)
            ->assertDontSee('<b>tebal</b>', false)
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('<i>miring</i>', false);
        $this->assertStringContainsString("Baris satu &lt;script&gt;alert(1)&lt;/script&gt;\n\nBaris dua", $response->getContent());
    }

    public function test_the_stylesheet_preserves_answer_line_breaks(): void
    {
        $scss = (string) file_get_contents(resource_path('scss/_public.scss'));

        $this->assertMatchesRegularExpression('/\.faq-answer\s*\{[^}]*white-space:\s*pre-line;/s', $scss);
    }

    // --- kosong dan tautan kontak --------------------------------------------------------------------------------------------

    public function test_with_no_published_faq_it_shows_the_empty_state(): void
    {
        $this->createFaq(['is_published' => false]);

        $response = $this->get(route('faq'))->assertOk();

        $response->assertSee('Belum ada pertanyaan yang diterbitkan')
            ->assertSee('Hubungi kami bila Anda memiliki pertanyaan.')
            ->assertDontSee('accordion', false)
            ->assertDontSee('<h2 class="h4 mb-3"', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_the_contact_link_appears_only_when_the_route_exists(): void
    {
        $this->createFaq();

        if (! Route::has('contact')) {
            $this->get(route('faq'))->assertOk()->assertDontSee('Hubungi kami</a>', false);

            $this->fakeRoute('contact', '/_uji/kontak');
        }

        $this->get(route('faq'))->assertOk()->assertSee('<p class="mb-0">Belum menemukan jawaban? <a href="'.route('contact').'">Hubungi kami</a>.</p>', false);
    }

    public function test_the_empty_state_links_to_contact_when_the_route_exists(): void
    {
        $this->fakeRoute('contact', '/_uji/kontak');

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Belum ada pertanyaan yang diterbitkan')
            ->assertSee('<a class="btn btn-outline-primary" href="'.route('contact').'">Hubungi kami</a>', false)
            ->assertDontSee('Belum menemukan jawaban?');
    }

    // --- mode ketat dan keamanan markup ---------------------------------------------------------------------------------------

    public function test_many_faqs_render_in_strict_mode_without_inline_handlers(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        foreach (Faq::CATEGORIES as $category) {
            foreach (range(1, 3) as $number) {
                $this->createFaq(['category' => $category, 'question' => "Pertanyaan {$category} {$number}?", 'sort_order' => $number]);
            }
        }

        $html = (string) $this->get(route('faq'))->assertOk()->getContent();

        $this->assertSame(12, substr_count($html, '<h3 class="accordion-header">'));
        $this->assertSame(4, substr_count($html, '<h2 class="h4 mb-3"'));
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_the_page_is_identical_for_guests_and_staff(): void
    {
        $this->createFaq(['question' => 'Terbuka untuk semua?']);

        $this->get(route('faq'))->assertOk()->assertSee('Terbuka untuk semua?');
        $this->actingAs(User::factory()->patient()->create())->get(route('faq'))->assertOk()->assertSee('Terbuka untuk semua?');

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get(route('faq'))->assertOk()->assertSee('Terbuka untuk semua?');
        }
    }
}
