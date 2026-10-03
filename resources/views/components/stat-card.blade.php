@props(['label', 'value', 'icon' => null, 'variant' => 'primary', 'hint' => null, 'href' => null])
@php
    $variant = in_array($variant, ['primary', 'success', 'info', 'warning'], true) ? $variant : 'primary';
    $labelId = 'stat-'.\Illuminate\Support\Str::slug((string) $label);
@endphp
<div {{ $attributes->merge(['class' => 'card stat-card border-0 shadow-sm position-relative']) }}>
    <div class="card-body d-flex align-items-start gap-3">
        @if ($icon)
            <span class="text-bg-{{ $variant }} rounded-3 d-inline-flex align-items-center justify-content-center flex-shrink-0 p-3 fs-4"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
        @endif
        <div class="flex-grow-1">
            <p id="{{ $labelId }}-label" class="mb-1 text-secondary fw-semibold">@if ($href)<a href="{{ $href }}" class="stretched-link text-decoration-none text-reset">{{ $label }}</a>@else{{ $label }}@endif</p>
            <p class="stat-card-value mb-0" aria-labelledby="{{ $labelId }}-label">{{ $value }}</p>
            @if ($hint)
                <p class="small text-secondary mb-0 mt-1">{{ $hint }}</p>
            @endif
        </div>
    </div>
</div>
