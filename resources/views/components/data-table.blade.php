@props([
    'rows',
    'searchName' => 'q',
    'searchPlaceholder' => 'Cari…',
    'caption' => 'Daftar data',
    'emptyTitle' => 'Belum ada data',
    'emptyMessage' => null,
    'searchable' => true,
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
        @isset($empty)
            {{ $empty }}
        @else
            <x-empty-state :title="$emptyTitle" :message="$hasQuery ? 'Tidak ada data yang cocok dengan pencarian atau filter Anda.' : $emptyMessage" :icon="$hasQuery ? 'bi-search' : 'bi-inbox'">
                @if ($hasQuery)<a href="{{ $currentUrl }}" class="btn btn-outline-primary">Hapus filter</a>@endif
            </x-empty-state>
        @endisset
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
