<?php

namespace Tests\Feature\Admin;

use App\Models\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin #39 tab FAQ (T10): daftar dengan filter kategori/status, CRUD, hapus permanen (CRD-13), audit,
 * dan akses per peran (AKS-04).
 */
class AdminFaqsTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'pendaftaran',
            'question' => 'Bagaimana cara mendaftar?',
            'answer' => 'Pilih dokter, pilih jadwal, lalu isi data diri Anda.',
            'sort_order' => '3',
            'is_published' => '1',
        ], $overrides);
    }

    /**
     * Empat FAQ dengan kategori, urutan, dan status berbeda.
     *
     * @return array{Faq, Faq, Faq, Faq}
     */
    private function seedFour(): array
    {
        return [
            $this->createFaq(['category' => 'pendaftaran', 'question' => 'Bagaimana cara mendaftar online?', 'sort_order' => 1]),
            $this->createFaq(['category' => 'bpjs', 'question' => 'Apakah BPJS diterima?', 'sort_order' => 2]),
            $this->createFaq(['category' => 'layanan', 'question' => 'Jam berapa poliklinik buka?', 'sort_order' => 3, 'is_published' => false]),
            $this->createFaq(['category' => 'umum', 'question' => 'Di mana lokasi rumah sakit?', 'sort_order' => 4]),
        ];
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function staffRoles(): array
    {
        return [
            'super admin' => ['super-admin', 200],
            'admin konten' => ['content-admin', 200],
            'petugas pendaftaran' => ['registration-officer', 403],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_index_access_per_staff_role(string $role, int $status): void
    {
        $this->createFaq(['question' => 'Pertanyaan akses?']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.faqs.index'))
            ->assertStatus($status);
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.faqs.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.faqs.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $faq = $this->createFaq();

        $this->assertSame(url('/admin/konten/faq'), route('admin.faqs.index'));
        $this->assertSame(url('/admin/konten/faq/buat'), route('admin.faqs.create'));
        $this->assertSame(url('/admin/konten/faq/'.$faq->id.'/ubah'), route('admin.faqs.edit', $faq));
        $this->assertSame(url('/admin/konten/faq/'.$faq->id), route('admin.faqs.update', $faq));
    }

    public function test_index_renders_many_rows_with_category_labels_order_and_status(): void
    {
        [$a, $b, $c] = $this->seedFour();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.faqs.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Bagaimana cara mendaftar online?',
                'Apakah BPJS diterima?',
                'Jam berapa poliklinik buka?',
                'Di mana lokasi rumah sakit?',
            ])
            ->assertSee('data-label="Kategori">Pendaftaran</td>', false)
            ->assertSee('data-label="Kategori">BPJS</td>', false)
            ->assertSee('data-label="Kategori">Layanan</td>', false)
            ->assertSee('data-label="Kategori">Umum</td>', false)
            ->assertSee('<span class="badge text-bg-success">Terbit</span>', false)
            ->assertSee('<span class="badge text-bg-secondary">Draf</span>', false)
            ->assertSee('href="'.route('admin.faqs.edit', $a).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.faqs.destroy', $b).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.faqs.destroy', $c).'"', false);
    }

    public function test_index_shows_module_tabs_breadcrumb_and_add_button(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.faqs.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.faqs.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Konten')
            ->assertSee('Tambah FAQ')
            ->assertSee('href="'.route('admin.faqs.create').'"', false);
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.faqs.index'))
            ->assertOk()
            ->assertSee('Belum ada FAQ');
    }

    public function test_filters_by_category_status_search_and_sort(): void
    {
        $this->seedFour();
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.faqs.index', ['kategori' => 'bpjs']))
            ->assertOk()->assertSee('Apakah BPJS diterima?')->assertDontSee('Di mana lokasi rumah sakit?');

        $this->get(route('admin.faqs.index', ['status' => 'draf']))
            ->assertOk()->assertSee('Jam berapa poliklinik buka?')->assertDontSee('Apakah BPJS diterima?');

        $this->get(route('admin.faqs.index', ['status' => 'terbit']))
            ->assertOk()->assertSee('Apakah BPJS diterima?')->assertDontSee('Jam berapa poliklinik buka?');

        $this->get(route('admin.faqs.index', ['q' => 'lokasi']))
            ->assertOk()->assertSee('Di mana lokasi rumah sakit?')->assertDontSee('Apakah BPJS diterima?');

        $this->get(route('admin.faqs.index', ['urut' => 'pertanyaan']))
            ->assertSeeInOrder([
                'Apakah BPJS diterima?',
                'Bagaimana cara mendaftar online?',
                'Di mana lokasi rumah sakit?',
                'Jam berapa poliklinik buka?',
            ]);
    }

    public function test_invalid_filters_are_ignored_and_array_params_do_not_cause_a_server_error(): void
    {
        $this->seedFour();
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.faqs.index', ['kategori' => 'tidak-ada', 'status' => 'zzz', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Apakah BPJS diterima?')
            ->assertSee('Di mana lokasi rumah sakit?')
            ->assertDontSee('situs-lain.test');

        $this->get(route('admin.faqs.index', ['kategori' => ['x'], 'status' => ['x'], 'urut' => ['x'], 'q' => ['x']]))
            ->assertOk();
    }

    public function test_create_form_has_the_four_categories_and_no_slug_field(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.faqs.create'))
            ->assertOk()
            ->assertSee('Tambah FAQ')
            ->assertSee('action="'.route('admin.faqs.store').'"', false)
            ->assertSee('name="category"', false)
            ->assertSee('<option value="pendaftaran"', false)
            ->assertSee('<option value="bpjs"', false)
            ->assertSee('<option value="layanan"', false)
            ->assertSee('<option value="umum"', false)
            ->assertSee('name="question"', false)
            ->assertSee('name="answer"', false)
            ->assertSee('name="sort_order"', false)
            ->assertSee('name="is_published"', false)
            ->assertDontSee('name="slug"', false);
    }

    public function test_content_admin_adds_a_faq_and_it_is_audited_crd10(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.faqs.store'), $this->payload())
            ->assertRedirect(route('admin.faqs.index'))
            ->assertSessionHas('success', 'FAQ berhasil ditambahkan.');

        $faq = Faq::query()->sole();

        $this->assertSame('pendaftaran', $faq->category);
        $this->assertSame('Bagaimana cara mendaftar?', $faq->question);
        $this->assertSame('Pilih dokter, pilih jadwal, lalu isi data diri Anda.', $faq->answer);
        $this->assertSame(3, $faq->sort_order);
        $this->assertTrue($faq->is_published);
        $this->assertSame('Pendaftaran', $faq->category_label);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'faq.created',
            'description' => 'FAQ "Bagaimana cara mendaftar?" ditambahkan.',
        ]);
    }

    public function test_sort_order_defaults_to_zero_and_a_faq_can_be_saved_as_draft(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.faqs.store'), $this->payload(['sort_order' => '', 'is_published' => '0']))
            ->assertSessionHasNoErrors();

        $faq = Faq::query()->sole();

        $this->assertSame(0, $faq->sort_order);
        $this->assertFalse($faq->is_published);
        $this->assertNotContains($faq->id, Faq::query()->published()->pluck('id')->all());
    }

    public function test_answer_keeps_line_breaks_and_plain_comparison_symbols(): void
    {
        $answer = "Langkah 1: daftar.\nLangkah 2: datang saat tekanan <90 dan >100 diperiksa.";

        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.faqs.store'), $this->payload(['answer' => $answer]))
            ->assertSessionHasNoErrors();

        $this->assertSame($answer, Faq::query()->sole()->answer);
    }

    public function test_edit_form_shows_the_current_values(): void
    {
        $faq = $this->createFaq(['category' => 'bpjs', 'question' => 'Apakah BPJS diterima?', 'answer' => 'Ya, dengan rujukan.', 'sort_order' => 7]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.faqs.edit', $faq))
            ->assertOk()
            ->assertSee('Ubah FAQ')
            ->assertSee('action="'.route('admin.faqs.update', $faq).'"', false)
            ->assertSee('<option value="bpjs" selected>BPJS</option>', false)
            ->assertSee('value="Apakah BPJS diterima?"', false)
            ->assertSee('Ya, dengan rujukan.')
            ->assertSee('value="7"', false);
    }

    public function test_update_changes_fields_unpublishes_and_audits(): void
    {
        $faq = $this->createFaq(['category' => 'umum', 'question' => 'Pertanyaan lama?', 'is_published' => true]);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.faqs.update', $faq), $this->payload([
                'category' => 'layanan',
                'question' => 'Pertanyaan baru?',
                'answer' => 'Jawaban baru.',
                'sort_order' => '9',
                'is_published' => '0',
            ]))
            ->assertRedirect(route('admin.faqs.index'))
            ->assertSessionHas('success', 'FAQ berhasil diperbarui.');

        $faq->refresh();
        $this->assertSame('layanan', $faq->category);
        $this->assertSame('Pertanyaan baru?', $faq->question);
        $this->assertSame('Jawaban baru.', $faq->answer);
        $this->assertSame(9, $faq->sort_order);
        $this->assertFalse($faq->is_published);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'faq.updated',
            'description' => 'FAQ "Pertanyaan baru?" diperbarui.',
        ]);
    }

    public function test_deleting_a_faq_is_permanent_and_leaves_the_others_crd13(): void
    {
        [$a, $b] = $this->seedFour();

        $this->actingAs($this->createStaff('content-admin'))
            ->delete(route('admin.faqs.destroy', $a))
            ->assertRedirect(route('admin.faqs.index'))
            ->assertSessionHas('success', 'FAQ berhasil dihapus.');

        $this->assertDatabaseMissing('faqs', ['id' => $a->id]);
        $this->assertDatabaseHas('faqs', ['id' => $b->id]);
        $this->assertSame(3, Faq::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'faq.deleted',
            'description' => 'FAQ "Bagaimana cara mendaftar online?" dihapus.',
        ]);
    }

    public function test_super_admin_can_also_manage_faqs(): void
    {
        $this->actingAs($this->createStaff('super-admin'))
            ->post(route('admin.faqs.store'), $this->payload(['question' => 'Dibuat oleh super admin?']))
            ->assertRedirect(route('admin.faqs.index'));

        $this->assertDatabaseHas('faqs', ['question' => 'Dibuat oleh super admin?']);
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/konten/faq/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_patient_and_guest_cannot_use_faq_routes(): void
    {
        $faq = $this->createFaq(['question' => 'Pertanyaan aman?']);

        $requests = [
            ['GET', route('admin.faqs.create'), []],
            ['POST', route('admin.faqs.store'), $this->payload(['question' => 'Tidak boleh dibuat?'])],
            ['GET', route('admin.faqs.edit', $faq), []],
            ['PUT', route('admin.faqs.update', $faq), $this->payload(['question' => 'Diubah paksa?'])],
            ['DELETE', route('admin.faqs.destroy', $faq), []],
        ];

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user);

            foreach ($requests as [$method, $url, $data]) {
                $this->call($method, $url, $data)->assertForbidden();
            }
        }

        $this->app['auth']->forgetGuards();

        foreach ($requests as [$method, $url, $data]) {
            $this->call($method, $url, $data)->assertRedirect(route('admin.login'));
        }

        $this->assertSame('Pertanyaan aman?', $faq->fresh()->question);
        $this->assertDatabaseMissing('faqs', ['question' => 'Tidak boleh dibuat?']);
        $this->assertDatabaseHas('faqs', ['id' => $faq->id]);
    }
}
