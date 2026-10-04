@props([
    'rows',
    'searchName' => 'q',
    'searchPlaceholder' => 'Cari…',
    'caption' => 'Daftar data',
    'emptyTitle' => 'Belum ada data',
    'emptyMessage' => null,
    'searchable' => true,
    'preserve' => ['urut'],
])
@php
    $keyword = request()->query($searchName);
    $keyword = is_string($keyword) ? $keyword : '';
    $hasQuery = collect(request()->query())
        ->except(['page', 'per_page'])
        ->filter(fn ($value) => is_array($value) ? $value !== [] : trim((string) $value) !== '')
        ->isNotEmpty();
    $perPage = request()->query('per_page');
    $perPage = is_string($perPage) ? $perPage : '';
    // Parameter kueri yang ikut terkirim ulang oleh form pencarian (mis. urutan), bukan hasil input form.
    $preserved = collect($preserve)
        ->mapWithKeys(fn ($field) => [(string) $field => request()->query((string) $field)])
        ->filter(fn ($value) => is_string($value) && $value !== '')
        ->all();
    $outOfRange = $rows->isEmpty() && method_exists($rows, 'total') && $rows->total() > 0;
    $searchId = $searchName.'-search';
    $currentUrl = url()->current();
@endphp
<div data-table {{ $attributes }}>
    <form method="GET" action="{{ $currentUrl }}" role="search" aria-label="Cari dan saring data" class="card card-body bg-light border-0 mb-3">
        <div class="row g-3 align-items-end">
            @if ($searchable)
                <div class="col-12 col-md-5 col-lg-4">
                    <label for="{{ $searchId }}" class="form-label fw-semibold">Cari</label>
                    <input type="search" id="{{ $searchId }}" name="{{ $searchName }}" value="{{ $keyword }}" class="form-control" placeholder="{{ $searchPlaceholder }}" maxlength="100" autocomplete="off">
                </div>
            @endif
            {{ $filters ?? '' }}
            <div class="col-12 col-md-auto d-flex gap-2">
                @if ($perPage !== '')<input type="hidden" name="per_page" value="{{ $perPage }}">@endif
                @foreach ($preserved as $field => $fieldValue)<input type="hidden" name="{{ $field }}" value="{{ $fieldValue }}">@endforeach
                <x-loading-button icon="bi-search">Terapkan</x-loading-button>
                @if ($hasQuery)<a href="{{ $currentUrl }}" class="btn btn-outline-secondary">Reset</a>@endif
            </div>
        </div>
    </form>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <p class="mb-0 text-secondary" aria-live="polite" data-table-summary>@if ($rows->isNotEmpty())Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} data @else Tidak ada data untuk ditampilkan. @endif</p>
        @isset($actions)
            <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
        @endisset
    </div>

    @if ($rows->isEmpty())
        @if ($outOfRange)
            <x-empty-state title="Halaman yang diminta tidak berisi data." message="Data pada halaman ini mungkin sudah berubah." icon="bi-exclamation-circle">
                <a href="{{ $rows->withQueryString()->url(1) }}" class="btn btn-outline-primary">Kembali ke halaman 1</a>
            </x-empty-state>
        @else
            @isset($empty)
                {{ $empty }}
            @else
                <x-empty-state :title="$emptyTitle" :message="$hasQuery ? 'Tidak ada data yang cocok dengan pencarian atau filter Anda.' : $emptyMessage" :icon="$hasQuery ? 'bi-search' : 'bi-inbox'">
                    @if ($hasQuery)<a href="{{ $currentUrl }}" class="btn btn-outline-primary">Hapus filter</a>@endif
                </x-empty-state>
            @endisset
        @endif
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle table-cards bg-white">
                <caption class="visually-hidden">{{ $caption }}</caption>
                <thead class="table-light">
                    <tr>{{ $head ?? '' }}</tr>
                </thead>
                <tbody>
                    {{ $slot }}
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $rows->withQueryString()->links() }}</div>
    @endif
</div>
