@props(['title', 'subtitle' => null, 'icon' => null])
<div {{ $attributes->merge(['class' => 'page-header d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4']) }}>
    <div>
        <h1 class="mb-1">@if ($icon)<i class="bi {{ $icon }} me-2" aria-hidden="true"></i>@endif{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-secondary mb-0">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>
    @isset($actions)
        <div class="page-header-actions d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
