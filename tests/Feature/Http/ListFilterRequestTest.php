<?php

namespace Tests\Feature\Http;

use App\Http\Requests\ListFilterRequest;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Requests\StatusSortFilterRequest;
use Tests\TestCase;

class ListFilterRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_uji/filter', fn (ListFilterRequest $request) => response()->json([
            'filters' => $request->filters(),
            'per_page' => $request->perPage(),
            'warning' => session('warning'),
        ]));

        Route::middleware('web')->get('/_uji/filter-status', fn (StatusSortFilterRequest $request) => response()->json([
            'filters' => $request->filters(),
            'per_page' => $request->perPage(),
            'warning' => session('warning'),
        ]));
    }

    private function resolve(array $query, string $class = ListFilterRequest::class): ListFilterRequest
    {
        $request = $class::create('/_uji/filter', 'GET', $query);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        return $request;
    }

    public function test_valid_parameters_are_returned_as_filters(): void
    {
        $this->get('/_uji/filter?q=jantung&kategori=dokter-anak&per_page=50&page=2')
            ->assertOk()
            ->assertJsonCount(4, 'filters')
            ->assertJsonPath('filters.q', 'jantung')
            ->assertJsonPath('filters.kategori', 'dokter-anak')
            ->assertJsonPath('filters.per_page', '50')
            ->assertJsonPath('filters.page', '2')
            ->assertJsonPath('per_page', 50)
            ->assertJsonPath('warning', null);
    }

    public function test_val_07_invalid_parameters_are_ignored_without_server_error(): void
    {
        $this->get('/_uji/filter?status=zzz&kembali='.urlencode('https://situs-lain.test').'&q[]=x&per_page=7')
            ->assertOk()
            ->assertJsonPath('filters', [])
            ->assertJsonMissingPath('filters.kembali')
            ->assertJsonPath('per_page', 25)
            ->assertJsonPath('warning', 'Filter tidak valid dan diabaikan.');
    }

    public function test_val_07_internal_return_path_is_kept(): void
    {
        $this->get('/_uji/filter?kembali='.urlencode('/admin/x?y=1'))
            ->assertOk()
            ->assertJsonPath('filters.kembali', '/admin/x?y=1')
            ->assertJsonPath('warning', null);
    }

    /** @return array<string, array{string}> */
    public static function unsafeReturnTargets(): array
    {
        return [
            'url absolut' => ['https://evil.test'],
            'protokol relatif' => ['//evil.test'],
            'backslash' => ['/\\evil.test'],
            'javascript' => ['javascript:alert(1)'],
            'tab' => ["/\t/evil.test"],
            'header injection' => ["/x\r\nSet-Cookie: a=b"],
        ];
    }

    #[DataProvider('unsafeReturnTargets')]
    public function test_val_07_unsafe_return_targets_are_dropped(string $target): void
    {
        $this->get('/_uji/filter?kembali='.urlencode($target))
            ->assertOk()
            ->assertJsonMissingPath('filters.kembali')
            ->assertJsonPath('warning', null);
    }

    public function test_unknown_parameters_are_never_returned(): void
    {
        $this->get('/_uji/filter?foo=bar&q=a')
            ->assertOk()
            ->assertJsonCount(1, 'filters')
            ->assertJsonPath('filters.q', 'a');
    }

    public function test_status_and_sort_have_no_rules_in_the_base_request(): void
    {
        $this->get('/_uji/filter?status=active&urut=name')
            ->assertOk()
            ->assertJsonPath('filters', [])
            ->assertJsonPath('warning', null);
    }

    public function test_subclass_hooks_whitelist_status_and_sort_including_descending(): void
    {
        $this->get('/_uji/filter-status?status=active&urut=-name&q=a')
            ->assertOk()
            ->assertJsonPath('filters.status', 'active')
            ->assertJsonPath('filters.urut', '-name')
            ->assertJsonPath('filters.q', 'a')
            ->assertJsonPath('warning', null);

        $this->get('/_uji/filter-status?urut=created_at')
            ->assertOk()
            ->assertJsonPath('filters.urut', 'created_at');
    }

    public function test_subclass_ignores_values_outside_the_whitelist(): void
    {
        $this->get('/_uji/filter-status?status=zzz&urut=password')
            ->assertOk()
            ->assertJsonPath('filters', [])
            ->assertJsonPath('warning', 'Filter tidak valid dan diabaikan.');

        $this->get('/_uji/filter-status?urut=--name&status=ACTIVE')
            ->assertOk()
            ->assertJsonPath('filters', []);
    }

    public function test_array_parameters_do_not_cause_server_errors(): void
    {
        foreach (['q[]=x', 'page[]=1', 'per_page[]=10', 'kategori[]=a', 'kembali[]=/x'] as $query) {
            $this->get('/_uji/filter?'.$query)
                ->assertOk()
                ->assertJsonPath('filters', []);
        }

        foreach (['status[]=active', 'urut[]=name', 'status[a]=b'] as $query) {
            $this->get('/_uji/filter-status?'.$query)
                ->assertOk()
                ->assertJsonPath('filters', []);
        }
    }

    public function test_page_must_be_a_positive_integer(): void
    {
        foreach (['page=0', 'page=-1', 'page=abc', 'page=1.5'] as $query) {
            $this->get('/_uji/filter?'.$query)
                ->assertOk()
                ->assertJsonMissingPath('filters.page');
        }

        $this->get('/_uji/filter?page=1')->assertJsonPath('filters.page', '1');
    }

    public function test_keyword_is_limited_to_one_hundred_characters(): void
    {
        $this->get('/_uji/filter?q='.str_repeat('a', 100))
            ->assertJsonPath('filters.q', str_repeat('a', 100))
            ->assertJsonPath('warning', null);

        $this->get('/_uji/filter?q='.str_repeat('a', 101))
            ->assertOk()
            ->assertJsonMissingPath('filters.q')
            ->assertJsonPath('warning', 'Filter tidak valid dan diabaikan.');
    }

    public function test_empty_values_are_not_filters(): void
    {
        $this->get('/_uji/filter?q=&kategori=&per_page=&page=&kembali=')
            ->assertOk()
            ->assertJsonPath('filters', [])
            ->assertJsonPath('warning', null);
    }

    public function test_per_page_accepts_only_the_documented_options(): void
    {
        $this->assertSame([10, 25, 50], ListFilterRequest::PER_PAGE_OPTIONS);

        foreach ([10, 25, 50] as $option) {
            $this->assertSame($option, $this->resolve(['per_page' => (string) $option])->perPage());
        }

        $this->assertSame(25, $this->resolve([])->perPage());
        $this->assertSame(25, $this->resolve(['per_page' => '7'])->perPage());
        $this->assertSame(10, $this->resolve(['per_page' => '7'])->perPage(10));
        $this->assertSame(10, $this->resolve([])->perPage(10));
        $this->assertSame(50, $this->resolve(['per_page' => '50'])->perPage(10));
    }

    public function test_failed_validation_does_not_throw_and_flashes_a_warning_for_the_current_request(): void
    {
        $request = $this->resolve(['q' => ['x'], 'page' => 'abc']);

        $this->assertSame([], $request->filters());
        $this->assertSame('Filter tidak valid dan diabaikan.', session('warning'));
    }

    public function test_valid_input_does_not_flash_a_warning(): void
    {
        $this->resolve(['q' => 'andi']);

        $this->assertNull(session('warning'));
    }

    public function test_request_is_always_authorized_and_declares_the_documented_rules(): void
    {
        $request = new ListFilterRequest;

        $this->assertTrue($request->authorize());
        $this->assertSame(['q', 'kategori', 'per_page', 'page', 'kembali'], array_keys($request->rules()));

        $rules = (new StatusSortFilterRequest)->rules();

        $this->assertSame(['q', 'kategori', 'per_page', 'page', 'kembali', 'status', 'urut'], array_keys($rules));
    }
}
