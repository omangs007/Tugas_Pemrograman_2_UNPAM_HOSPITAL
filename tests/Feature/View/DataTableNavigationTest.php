<?php

namespace Tests\Feature\View;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class DataTableNavigationTest extends TestCase
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

    private function table(string $attributes = ''): string
    {
        return <<<BLADE
            <x-data-table :rows="\$rows" caption="Daftar dokter" empty-title="Belum ada dokter" empty-message="Tambahkan dokter pertama." {$attributes}>
                <x-slot:head><th scope="col">Nama</th></x-slot:head>
                @foreach (\$rows as \$row)
                    <tr><td data-label="Nama">{{ \$row['name'] }}</td></tr>
                @endforeach
            </x-data-table>
            BLADE;
    }

    public function test_sort_parameter_is_preserved_as_a_hidden_input_by_default(): void
    {
        $this->visit('/admin/dokter?urut=nama&q=andi&per_page=25');

        $html = (string) $this->blade($this->table(), ['rows' => $this->paginator(32)]);

        $this->assertSame(1, substr_count($html, '<input type="hidden" name="urut" value="nama">'));
        $this->assertSame(1, substr_count($html, '<input type="hidden" name="per_page" value="25">'));
    }

    public function test_no_hidden_input_is_rendered_when_the_parameter_is_absent_or_not_a_string(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(32)])
            ->assertDontSee('name="urut"', false);

        $this->visit('/admin/dokter?urut[]=nama&status=');

        $this->blade($this->table(':preserve="[\'urut\', \'status\']"'), ['rows' => $this->paginator(32)])
            ->assertDontSee('name="urut"', false)
            ->assertDontSee('name="status"', false);
    }

    public function test_preserve_prop_accepts_custom_names_and_empty_list(): void
    {
        $this->visit('/admin/dokter?urut=terbaru&status=archived&poli=anak');

        $this->blade($this->table(':preserve="[\'urut\', \'status\']"'), ['rows' => $this->paginator(32)])
            ->assertSee('<input type="hidden" name="urut" value="terbaru">', false)
            ->assertSee('<input type="hidden" name="status" value="archived">', false)
            ->assertDontSee('name="poli"', false);

        $this->blade($this->table(':preserve="[]"'), ['rows' => $this->paginator(32)])
            ->assertDontSee('type="hidden"', false);
    }

    public function test_preserved_values_are_escaped(): void
    {
        $this->visit('/admin/dokter?'.http_build_query(['urut' => '"><script>alert(1)</script>']));

        $this->blade($this->table(), ['rows' => $this->paginator(32)])
            ->assertSee('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_page_beyond_the_last_shows_message_and_link_to_page_one(): void
    {
        $this->visit('/admin/dokter?q=andi&urut=nama&page=9');

        $this->blade($this->table(), ['rows' => $this->paginator(32, 10, 9)])
            ->assertSee('Halaman yang diminta tidak berisi data.')
            ->assertSee('Kembali ke halaman 1')
            ->assertSee('href="'.url('/admin/dokter').'?q=andi&amp;urut=nama&amp;page=1"', false)
            ->assertDontSee('<table', false)
            ->assertDontSee('Belum ada dokter')
            ->assertDontSee('Tidak ada data yang cocok');
    }

    public function test_truly_empty_result_keeps_the_regular_empty_state(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(0)])
            ->assertSee('Belum ada dokter')
            ->assertSee('Tambahkan dokter pertama.')
            ->assertDontSee('Halaman yang diminta tidak berisi data.')
            ->assertDontSee('Kembali ke halaman 1');
    }

    public function test_page_within_range_shows_rows_without_the_out_of_range_message(): void
    {
        $this->visit('/admin/dokter?page=2');

        $this->blade($this->table(), ['rows' => $this->paginator(32, 10, 2)])
            ->assertSee('Dokter 11')
            ->assertDontSee('Halaman yang diminta tidak berisi data.');
    }
}
