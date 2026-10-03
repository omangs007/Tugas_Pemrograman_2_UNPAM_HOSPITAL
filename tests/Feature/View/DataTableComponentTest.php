<?php

namespace Tests\Feature\View;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class DataTableComponentTest extends TestCase
{
    private function visit(string $uri): void
    {
        $this->app->instance('request', Request::create($uri));
    }

    private function paginator(int $total, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $items = collect($total > 0 ? range(1, $total) : [])
            ->forPage($page, $perPage)
            ->values()
            ->map(fn (int $number): array => ['id' => $number, 'name' => "Dokter {$number}"]);

        return new LengthAwarePaginator($items, $total, $perPage, $page, ['path' => url('/admin/dokter')]);
    }

    private function table(): string
    {
        return <<<'BLADE'
            <x-data-table :rows="$rows" caption="Daftar dokter" search-placeholder="Cari dokter…" empty-title="Belum ada dokter" empty-message="Tambahkan dokter pertama.">
                <x-slot:filters>
                    <div class="col-12 col-md-3"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-select"></select></div>
                </x-slot:filters>
                <x-slot:actions><a href="/admin/dokter/baru" class="btn btn-primary">Tambah Dokter</a></x-slot:actions>
                <x-slot:head><th scope="col">Nama</th><th scope="col">Aksi</th></x-slot:head>
                @foreach ($rows as $row)
                    <tr><td data-label="Nama">{{ $row['name'] }}</td><td data-label="Aksi" class="table-actions"><a href="#">Ubah</a></td></tr>
                @endforeach
            </x-data-table>
            BLADE;
    }

    public function test_bootstrap_five_is_the_default_pagination_view(): void
    {
        $this->assertSame('pagination::bootstrap-5', Paginator::$defaultView);
    }

    public function test_table_renders_search_form_slots_caption_and_card_markup(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(32)])
            ->assertSee('data-table', false)
            ->assertSee('method="GET"', false)
            ->assertSee('role="search"', false)
            ->assertSee('aria-label="Cari dan saring data"', false)
            ->assertSee('type="search"', false)
            ->assertSee('name="q"', false)
            ->assertSee('placeholder="Cari dokter…"', false)
            ->assertSee('id="status"', false)
            ->assertSee('Tambah Dokter')
            ->assertSee('<caption class="visually-hidden">Daftar dokter</caption>', false)
            ->assertSee('table-cards', false)
            ->assertSee('<th scope="col">Nama</th>', false)
            ->assertSee('<td data-label="Nama">Dokter 1</td>', false)
            ->assertSee('data-loading-button', false)
            ->assertSee('Terapkan')
            ->assertDontSee('Hapus filter')
            ->assertDontSee('>Reset</a>', false);
    }

    public function test_summary_shows_visible_range_and_total(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(32)])
            ->assertSee('Menampilkan 1–10 dari 32 data')
            ->assertSee('aria-live="polite"', false);

        $this->blade($this->table(), ['rows' => $this->paginator(32, 10, 2)])
            ->assertSee('Menampilkan 11–20 dari 32 data');

        $this->blade($this->table(), ['rows' => $this->paginator(32, 10, 4)])
            ->assertSee('Menampilkan 31–32 dari 32 data');
    }

    public function test_pagination_is_indonesian_accessible_and_keeps_the_query_string(): void
    {
        $this->visit('/admin/dokter?q=andi&status=active');

        $this->blade($this->table(), ['rows' => $this->paginator(32)])
            ->assertSee('aria-label="Navigasi halaman"', false)
            ->assertSee('Sebelumnya')
            ->assertSee('Berikutnya')
            ->assertSee('rel="next"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Menampilkan <span class="fw-semibold">1</span> sampai <span class="fw-semibold">10</span> dari <span class="fw-semibold">32</span> hasil', false)
            ->assertSee('/admin/dokter?q=andi&amp;status=active&amp;page=2', false)
            ->assertDontSee('Previous')
            ->assertDontSee('Next')
            ->assertDontSee('Pagination Navigation');
    }

    public function test_single_page_has_no_pagination_navigation(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(5)])
            ->assertSee('Menampilkan 1–5 dari 5 data')
            ->assertDontSee('Navigasi halaman', false);
    }

    public function test_simple_pagination_view_is_indonesian(): void
    {
        $paginator = new Paginator(collect(range(1, 11)), 10, 1, ['path' => url('/admin/dokter')]);

        $html = (string) $paginator->links('pagination::simple-bootstrap-5');

        $this->assertStringContainsString('aria-label="Navigasi halaman"', $html);
        $this->assertStringContainsString('Sebelumnya', $html);
        $this->assertStringContainsString('Berikutnya', $html);
        $this->assertStringNotContainsString('Pagination Navigation', $html);
    }

    public function test_keyword_is_kept_in_the_search_input_and_escaped(): void
    {
        $this->visit('/admin/dokter?q='.urlencode('"><script>alert(1)</script>'));

        $this->blade($this->table(), ['rows' => $this->paginator(3)])
            ->assertSee('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_array_query_values_do_not_break_the_table(): void
    {
        $this->visit('/admin/dokter?q[]=x&status[a]=b');

        $this->blade($this->table(), ['rows' => $this->paginator(3)])
            ->assertSee('type="search"', false)
            ->assertDontSee('value="Array"', false);
    }

    public function test_empty_state_for_a_table_without_data_emp_03(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(0)])
            ->assertSee('role="status"', false)
            ->assertSee('Belum ada dokter')
            ->assertSee('Tambahkan dokter pertama.')
            ->assertSee('Tidak ada data untuk ditampilkan.')
            ->assertDontSee('<table', false)
            ->assertDontSee('Hapus filter')
            ->assertDontSee('>Reset</a>', false)
            ->assertDontSee('Navigasi halaman', false);
    }

    public function test_empty_state_with_a_query_offers_to_clear_filters(): void
    {
        $this->visit('/admin/dokter?q=zzz&per_page=25&page=1');

        $this->blade($this->table(), ['rows' => $this->paginator(0)])
            ->assertSee('Belum ada dokter')
            ->assertSee('Tidak ada data yang cocok dengan pencarian atau filter Anda.')
            ->assertDontSee('Tambahkan dokter pertama.')
            ->assertSee('<a href="'.url('/admin/dokter').'" class="btn btn-outline-primary">Hapus filter</a>', false)
            ->assertSee('>Reset</a>', false);
    }

    public function test_per_page_and_page_alone_do_not_count_as_a_filter(): void
    {
        $this->visit('/admin/dokter?per_page=50&page=3');

        $this->blade($this->table(), ['rows' => $this->paginator(0)])
            ->assertDontSee('Hapus filter')
            ->assertSee('<input type="hidden" name="per_page" value="50">', false);
    }

    public function test_empty_slot_replaces_the_default_empty_state(): void
    {
        $this->visit('/admin/dokter');

        $this->blade(<<<'BLADE'
            <x-data-table :rows="$rows" caption="Daftar">
                <x-slot:empty><p>Kustom kosong</p></x-slot:empty>
            </x-data-table>
            BLADE, ['rows' => $this->paginator(0)])
            ->assertSee('Kustom kosong')
            ->assertDontSee('Belum ada data');
    }

    public function test_search_can_be_disabled_and_renamed(): void
    {
        $this->visit('/admin/dokter');

        $this->blade('<x-data-table :rows="$rows" caption="Daftar" :searchable="false"><tr><td data-label="Nama">x</td></tr></x-data-table>', ['rows' => $this->paginator(1)])
            ->assertSee('role="search"', false)
            ->assertDontSee('type="search"', false);

        $this->blade('<x-data-table :rows="$rows" caption="Daftar" search-name="cari"><tr><td data-label="Nama">x</td></tr></x-data-table>', ['rows' => $this->paginator(1)])
            ->assertSee('name="cari"', false)
            ->assertDontSee('name="q"', false);
    }

    public function test_caption_is_escaped(): void
    {
        $this->visit('/admin/dokter');

        $this->blade('<x-data-table :rows="$rows" :caption="$caption"><tr><td data-label="Nama">x</td></tr></x-data-table>', [
            'rows' => $this->paginator(1),
            'caption' => '<script>alert(1)</script>',
        ])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
