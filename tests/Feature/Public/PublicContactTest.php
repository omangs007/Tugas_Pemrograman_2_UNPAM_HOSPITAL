<?php

namespace Tests\Feature\Public;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #22 `contact` (05 hal. 22, 07 W-08, F4B-R32): alamat selalu tampil; kontak dari pengaturan dirender apa
 * adanya dan baris kosong disembunyikan; tombol Petunjuk Arah dan WhatsApp; peta `x-map-embed`; form dengan honeypot,
 * catatan privasi, dan persetujuan. Mencakup CRD-12, EMP-01, OFF-04, dan A11-05.
 */
class PublicContactTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    private function section(string $html, string $labelledBy): string
    {
        $found = preg_match('#<section[^>]*aria-labelledby="'.preg_quote($labelledBy, '#').'".*?</section>#s', $html, $matches);

        $this->assertSame(1, $found, "Bagian {$labelledBy} tidak ditemukan.");

        return $matches[0];
    }

    private function seedSettings(): void
    {
        $this->seed(SettingsSeeder::class);
    }

    private function noindexTag(): string
    {
        return '<meta name="robots" content="noindex,nofollow">';
    }

    // --- header, SEO, breadcrumb -------------------------------------------------------------------------

    public function test_it_renders_the_default_header_seo_and_breadcrumb(): void
    {
        $response = $this->get(route('contact'))->assertOk();

        $response->assertSee('<title>Kontak dan Lokasi — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Kontak dan Lokasi</h1>', false)
            ->assertSee('Hubungi kami atau kunjungi kami')
            ->assertSee('<meta name="description" content="Alamat, telepon, WhatsApp, e-mail, peta, dan formulir kontak UNPAM HOSPITAL.">', false)
            ->assertSee('<meta property="og:title" content="Kontak dan Lokasi">', false)
            ->assertSee('<link rel="canonical" href="'.route('contact').'">', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Kontak dan Lokasi'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Kontak dan Lokasi</li>', false)
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_the_header_and_seo_follow_the_contact_page_row(): void
    {
        $this->createPage('contact', [
            'title' => 'Hubungi Kami',
            'subtitle' => 'Kami siap membantu',
            'meta_title' => 'Hubungi Kami Meta',
            'meta_description' => 'Deskripsi hubungi kami.',
        ]);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('<title>Hubungi Kami Meta — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Hubungi Kami</h1>', false)
            ->assertSee('Kami siap membantu')
            ->assertSee('<meta name="description" content="Deskripsi hubungi kami.">', false);
    }

    // --- alamat dan kontak (CRD-12) ----------------------------------------------------------------------------

    public function test_the_address_card_shows_the_settings_as_they_are(): void
    {
        $this->seedSettings();

        $card = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-alamat');

        $this->assertStringContainsString('<h2 id="judul-alamat" class="h5 mb-3">Alamat</h2>', $card);
        $this->assertStringContainsString('<address class="mb-3">Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek, Buaran, Kecamatan Pamulang, Kota Tangerang Selatan, Banten 15310</address>', $card);
        $this->assertStringContainsString('<li>Telepon: <a href="tel:02100000000">(021) 0000-0000</a></li>', $card);
        $this->assertStringContainsString('<li>IGD: <a href="tel:02100000119">(021) 0000-0119</a></li>', $card);
        $this->assertStringContainsString('<li>WhatsApp: 6280000000000</li>', $card);
        $this->assertStringContainsString('<li>Email: <a href="mailto:info@unpamhospital.test">info@unpamhospital.test</a></li>', $card);
        $this->assertStringContainsString('<li>Jam operasional: Senin–Sabtu 08.00–20.00 (contoh)</li>', $card);
        $this->assertStringNotContainsString('(placeholder)', $card);
    }

    public function test_changing_the_settings_changes_the_page_and_the_footer(): void
    {
        $this->seedSettings();
        app(SettingsService::class)->setMany([
            'contact.address' => 'Jl. Baru No. 1, Pamulang',
            'contact.phone' => '(021) 5555-1234',
            'contact.email' => 'baru@contoh.test',
            'contact.operational_hours' => 'Setiap hari 24 jam',
        ]);

        $html = (string) $this->get(route('contact'))->assertOk()->getContent();
        $card = $this->section($html, 'judul-alamat');

        $this->assertStringContainsString('<address class="mb-3">Jl. Baru No. 1, Pamulang</address>', $card);
        $this->assertStringContainsString('<a href="tel:02155551234">(021) 5555-1234</a>', $card);
        $this->assertStringContainsString('mailto:baru@contoh.test', $card);
        $this->assertStringContainsString('Setiap hari 24 jam', $card);
        $this->assertStringNotContainsString('Pamulang Kampus 2 Viktor, Jl. Raya', $card);
        $this->assertStringContainsString('<address class="mb-0">Jl. Baru No. 1, Pamulang</address>', $html);
    }

    public function test_empty_settings_hide_their_rows_but_the_address_stays(): void
    {
        $response = $this->get(route('contact'))->assertOk();
        $card = $this->section($response->getContent(), 'judul-alamat');

        $this->assertStringContainsString('<address class="mb-3">Alamat belum diisi.</address>', $card);

        foreach (['Telepon:', 'IGD:', 'WhatsApp:', 'Email:', 'Jam operasional:', 'Petunjuk Arah', 'Chat via WhatsApp', '<ul'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $card);
        }

        $response->assertSee('Peta tidak tersedia.')
            ->assertDontSee('<iframe', false);
    }

    public function test_a_number_without_digits_renders_no_empty_tel_link(): void
    {
        $this->seedSettings();
        app(SettingsService::class)->setMany(['contact.phone' => '-', 'contact.emergency_phone' => '-', 'contact.whatsapp' => '-']);

        $card = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-alamat');

        $this->assertStringNotContainsString('href="tel:"', $card);
        $this->assertStringContainsString('<li>Telepon: -</li>', $card);
        $this->assertStringNotContainsString('WhatsApp:', $card);
        $this->assertStringNotContainsString('Chat via WhatsApp', $card);
    }

    public function test_the_directions_and_whatsapp_buttons_open_in_a_new_tab_safely(): void
    {
        $this->seedSettings();

        $card = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-alamat');
        $directions = e((string) app(SettingsService::class)->string('map.directions_url'));

        $this->assertStringContainsString('<a class="btn btn-primary" href="'.$directions.'" target="_blank" rel="noopener noreferrer"><i class="bi bi-geo-alt" aria-hidden="true"></i> Petunjuk Arah</a>', $card);
        $this->assertStringContainsString('<a class="btn btn-outline-primary" href="https://wa.me/6280000000000" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i> Chat via WhatsApp (membuka WhatsApp)</a>', $card);
    }

    public function test_an_unsafe_directions_url_is_not_rendered(): void
    {
        $this->seedSettings();
        app(SettingsService::class)->setMany(['map.directions_url' => 'javascript:alert(1)']);

        $html = (string) $this->get(route('contact'))->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<i class="bi bi-geo-alt" aria-hidden="true"></i> Petunjuk Arah</a>', $this->section($html, 'judul-alamat'));
    }

    // --- peta ---------------------------------------------------------------------------------------------------------

    public function test_the_map_card_embeds_the_map_with_its_fallback(): void
    {
        $this->seedSettings();

        $card = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-peta');

        $this->assertStringContainsString('<h2 id="judul-peta" class="h5 mb-3">Peta</h2>', $card);
        $this->assertStringContainsString('data-map-embed', $card);
        $this->assertStringContainsString('data-map-fallback', $card);
        $this->assertStringContainsString('role="status"', $card);
        $this->assertStringContainsString('Memuat peta…', $card);
        $this->assertStringContainsString('title="Peta lokasi UNPAM HOSPITAL"', $card);
        $this->assertStringContainsString('loading="lazy"', $card);
        $this->assertStringContainsString('output=embed', $card);
    }

    // --- formulir -------------------------------------------------------------------------------------------------------

    public function test_the_form_has_the_labelled_fields_with_the_right_attributes(): void
    {
        $response = $this->get(route('contact'))->assertOk();
        $form = $this->section($response->getContent(), 'judul-form-kontak');

        $this->assertStringContainsString('<h2 id="judul-form-kontak" class="h5 mb-3">Kirim Pesan</h2>', $form);
        $this->assertStringContainsString('method="POST" action="'.route('contact.store').'"', $form);
        $this->assertStringContainsString('name="_token"', $form);
        $this->assertStringContainsString('<input type="text" id="name" name="name"', $form);
        $this->assertStringContainsString('autocomplete="name"', $form);
        $this->assertStringContainsString('<input type="email" id="email" name="email"', $form);
        $this->assertStringContainsString('autocomplete="email"', $form);
        $this->assertStringContainsString('<input type="tel" id="phone" name="phone"', $form);
        $this->assertStringContainsString('inputmode="tel"', $form);
        $this->assertStringContainsString('<input type="text" id="subject" name="subject"', $form);
        $this->assertStringContainsString('<textarea id="message" name="message"', $form);
        $this->assertStringContainsString('maxlength="2000"', $form);

        foreach (['Nama', 'Email', 'Telepon (opsional)', 'Subjek', 'Pesan'] as $label) {
            $this->assertStringContainsString('class="form-label fw-semibold">'.$label, $form);
        }

        $this->assertStringContainsString('Kolom bertanda', $form);
        $this->assertStringNotContainsString('data-errors-summary', $form);
    }

    public function test_the_message_field_carries_the_privacy_note(): void
    {
        $form = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-form-kontak');

        $this->assertStringContainsString('Jangan menuliskan data medis atau nomor identitas pada pesan ini.', $form);
    }

    public function test_the_honeypot_is_hidden_from_people_and_assistive_technology(): void
    {
        $form = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-form-kontak');

        $this->assertStringContainsString('<div class="d-none" aria-hidden="true">', $form);
        $this->assertStringContainsString('<label for="website">Situs web</label>', $form);
        $this->assertStringContainsString('<input type="text" id="website" name="website" tabindex="-1" autocomplete="off">', $form);
    }

    public function test_the_consent_checkbox_links_to_the_privacy_policy(): void
    {
        $form = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-form-kontak');

        $this->assertStringContainsString('<input type="checkbox" id="consent" name="consent" value="1"', $form);
        $this->assertStringContainsString('Saya menyetujui data saya diproses sesuai <a href="'.route('privacy').'">Kebijakan Privasi</a>.', $form);
        $this->assertStringContainsString('aria-required="true"', $form);
    }

    public function test_the_submit_button_is_a_loading_button(): void
    {
        $form = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-form-kontak');

        $this->assertStringContainsString('data-loading-button', $form);
        $this->assertStringContainsString('data-loading-text="Mengirim…"', $form);
        $this->assertStringContainsString('<span data-loading-label>Kirim Pesan</span>', $form);
    }

    public function test_the_sections_follow_the_documented_order_and_layout(): void
    {
        $html = (string) $this->get(route('contact'))->assertOk()->getContent();

        $this->assertSeeInOrderIn($html, ['id="judul-alamat"', 'id="judul-peta"', 'id="judul-form-kontak"']);
        $this->assertSame(2, substr_count($html, 'class="col-lg-6"'));
        $this->assertSame(3, preg_match_all('/class="card(?:\s[^"]*)?"/', $html), 'Tiga kartu: alamat, peta, dan form.');
    }

    public function test_the_success_flash_is_shown_on_the_page(): void
    {
        $this->withSession(['success' => 'Terima kasih. Pesan Anda telah kami terima dan akan ditindaklanjuti oleh petugas kami.'])
            ->get(route('contact'))
            ->assertOk()
            ->assertSee('data-flash="success"', false)
            ->assertSee('Terima kasih. Pesan Anda telah kami terima dan akan ditindaklanjuti oleh petugas kami.');
    }

    // --- akses dan keamanan markup -------------------------------------------------------------------------------------------

    public function test_the_page_is_identical_for_guests_patients_and_staff(): void
    {
        $this->seedSettings();

        foreach ([null, 'patient', 'content-admin', 'registration-officer', 'super-admin'] as $actor) {
            if ($actor === 'patient') {
                $this->actingAs(User::factory()->patient()->create());
            } elseif ($actor !== null) {
                $this->actingAs($this->createStaff($actor));
            }

            $this->get(route('contact'))->assertOk()->assertSee('Kirim Pesan')->assertSee('id="judul-alamat"', false);
        }
    }

    public function test_the_page_has_no_inline_handlers_and_no_raw_output(): void
    {
        $this->seedSettings();

        $html = (string) $this->get(route('contact'))->assertOk()->getContent();

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $html));

        $source = (string) file_get_contents(resource_path('views/public/contact.blade.php'));
        $this->assertStringNotContainsString('{!!', $source);
        $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', $source));
    }

    public function test_settings_values_are_escaped(): void
    {
        $this->seedSettings();
        app(SettingsService::class)->setMany(['contact.address' => '<script>alert(1)</script> Jl. Uji', 'contact.operational_hours' => '<b>24 jam</b>']);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Jl. Uji', false)
            ->assertSee('&lt;b&gt;24 jam&lt;/b&gt;', false)
            ->assertDontSee('<script>alert(1)', false);
    }

    /** @param  list<string>  $needles */
    private function assertSeeInOrderIn(string $html, array $needles): void
    {
        $offset = -1;

        foreach ($needles as $needle) {
            $position = strpos($html, $needle);

            $this->assertNotFalse($position, "{$needle} tidak ditemukan.");
            $this->assertGreaterThan($offset, $position, "{$needle} berada di urutan yang salah.");
            $offset = $position;
        }
    }
}
